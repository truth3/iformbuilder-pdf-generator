<?php
declare(strict_types=1);

/**
 * JSON Web Token implementation, based on this spec:
 * http://tools.ietf.org/html/draft-ietf-oauth-json-web-token-06
 *
 * PHP version 8.4
 *
 * @category Authentication
 * @package  Authentication_JWT
 * @author   Neuman Vong <neuman@twilio.com>
 * @author   Anant Narayanan <anant@php.net>
 * @license  http://opensource.org/licenses/BSD-3-Clause 3-clause BSD
 * @link     https://github.com/firebase/php-jwt
 */

/**
 * Exception classes for JWT handling
 */
class BeforeValidException extends \UnexpectedValueException {}
class ExpiredException extends \UnexpectedValueException {}
class SignatureInvalidException extends \UnexpectedValueException {}

class JWT
{
    public static array $supported_algs = [
        'HS256' => ['hash_hmac', 'SHA256'],
        'HS512' => ['hash_hmac', 'SHA512'],
        'HS384' => ['hash_hmac', 'SHA384'],
        'RS256' => ['openssl', 'SHA256'],
    ];

    /**
     * Decodes a JWT string into a PHP object.
     *
     * @param string $jwt The JWT
     * @param string|array|null $key The secret key or map of keys
     * @param array $allowed_algs List of supported verification algorithms
     *
     * @return object The JWT's payload as a PHP object
     *
     * @throws \DomainException
     * @throws \UnexpectedValueException
     * @throws SignatureInvalidException
     * @throws BeforeValidException
     * @throws ExpiredException
     */
    public static function decode(string $jwt, string|array|null $key = null, array $allowed_algs = []): object
    {
        $tks = explode('.', $jwt);
        if (count($tks) != 3) {
            throw new \UnexpectedValueException('Wrong number of segments');
        }
        [$headb64, $bodyb64, $cryptob64] = $tks;
        
        $header = self::jsonDecode(self::urlsafeB64Decode($headb64));
        if ($header === null) {
            throw new \UnexpectedValueException('Invalid header encoding');
        }
        
        $payload = self::jsonDecode(self::urlsafeB64Decode($bodyb64));
        if ($payload === null) {
            throw new \UnexpectedValueException('Invalid claims encoding');
        }
        
        $sig = self::urlsafeB64Decode($cryptob64);
        
        if (isset($key)) {
            if (empty($header->alg)) {
                throw new \DomainException('Empty algorithm');
            }
            if (empty(self::$supported_algs[$header->alg])) {
                throw new \DomainException('Algorithm not supported');
            }
            if (!is_array($allowed_algs) || !in_array($header->alg, $allowed_algs)) {
                throw new \DomainException('Algorithm not allowed');
            }
            if (is_array($key)) {
                if (isset($header->kid)) {
                    if (!isset($key[$header->kid])) {
                        throw new \DomainException('"kid" invalid, unable to lookup correct key');
                    }
                    $key = $key[$header->kid];
                } else {
                    throw new \DomainException('"kid" empty, unable to lookup correct key');
                }
            }

            // Check the signature
            if (!self::verify("$headb64.$bodyb64", $sig, $key, $header->alg)) {
                throw new SignatureInvalidException('Signature verification failed');
            }

            // Check nbf (not before) claim
            if (isset($payload->nbf) && $payload->nbf > time()) {
                throw new BeforeValidException(
                    'Cannot handle token prior to ' . date(\DateTimeInterface::ISO8601, $payload->nbf)
                );
            }

            // Check iat (issued at) claim
            if (isset($payload->iat) && $payload->iat > time()) {
                throw new BeforeValidException(
                    'Cannot handle token prior to ' . date(\DateTimeInterface::ISO8601, $payload->iat)
                );
            }

            // Check exp (expiration) claim
            if (isset($payload->exp) && time() >= $payload->exp) {
                throw new ExpiredException('Expired token');
            }
        }

        return $payload;
    }

    /**
     * Converts and signs a PHP object or array into a JWT string.
     *
     * @param object|array $payload PHP object or array
     * @param string $key The secret key
     * @param string $alg The signing algorithm
     * @param string|null $keyId Optional key identifier
     *
     * @return string A signed JWT
     */
    public static function encode(object|array $payload, string $key, string $alg = 'HS256', ?string $keyId = null): string
    {
        $header = ['typ' => 'JWT', 'alg' => $alg];
        if ($keyId !== null) {
            $header['kid'] = $keyId;
        }
        
        $segments = [];
        $segments[] = self::urlsafeB64Encode(self::jsonEncode($header));
        $segments[] = self::urlsafeB64Encode(self::jsonEncode($payload));
        $signing_input = implode('.', $segments);

        $signature = self::sign($signing_input, $key, $alg);
        $segments[] = self::urlsafeB64Encode($signature);

        return implode('.', $segments);
    }

    /**
     * Sign a string with a given key and algorithm.
     *
     * @param string $msg The message to sign
     * @param string|resource $key The secret key
     * @param string $alg The signing algorithm
     *
     * @return string An encrypted message
     * @throws \DomainException
     */
    public static function sign(string $msg, string|resource $key, string $alg = 'HS256'): string
    {
        if (empty(self::$supported_algs[$alg])) {
            throw new \DomainException('Algorithm not supported');
        }
        
        [$function, $algorithm] = self::$supported_algs[$alg];
        
        switch ($function) {
            case 'hash_hmac':
                return hash_hmac($algorithm, $msg, $key, true);
            case 'openssl':
                $signature = '';
                $success = openssl_sign($msg, $signature, $key, $algorithm);
                if (!$success) {
                    throw new \DomainException("OpenSSL unable to sign data");
                }
                return $signature;
            default:
                throw new \DomainException("Unknown function $function");
        }
    }

    /**
     * Verify a signature with the message, key and method.
     *
     * @param string $msg The original message
     * @param string $signature The signature
     * @param string|resource $key The key
     * @param string $alg The algorithm
     *
     * @return bool
     * @throws \DomainException Invalid Algorithm or OpenSSL failure
     */
    private static function verify(string $msg, string $signature, string|resource $key, string $alg): bool
    {
        if (empty(self::$supported_algs[$alg])) {
            throw new \DomainException('Algorithm not supported');
        }

        [$function, $algorithm] = self::$supported_algs[$alg];
        
        switch ($function) {
            case 'openssl':
                $success = openssl_verify($msg, $signature, $key, $algorithm);
                if ($success === -1) {
                    throw new \DomainException("OpenSSL unable to verify data: " . openssl_error_string());
                }
                return $success === 1;
            case 'hash_hmac':
            default:
                $hash = hash_hmac($algorithm, $msg, $key, true);
                return hash_equals($signature, $hash);
        }
    }

    /**
     * Decode a JSON string into a PHP object.
     *
     * @param string $input JSON string
     *
     * @return object|null Object representation of JSON string
     * @throws \DomainException Provided string was invalid JSON
     */
    public static function jsonDecode(string $input): ?object
    {
        try {
            $obj = json_decode($input, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new \DomainException('JSON parse error: ' . $e->getMessage());
        }
        
        return $obj;
    }

    /**
     * Encode a PHP object into a JSON string.
     *
     * @param object|array $input A PHP object or array
     *
     * @return string JSON representation of the PHP object or array
     * @throws \DomainException Provided object could not be encoded to valid JSON
     */
    public static function jsonEncode(object|array $input): string
    {
        try {
            return json_encode($input, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \DomainException('JSON encode error: ' . $e->getMessage());
        }
    }

    /**
     * Decode a string with URL-safe Base64.
     *
     * @param string $input A Base64 encoded string
     *
     * @return string A decoded string
     */
    public static function urlsafeB64Decode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'), true) ?: '';
    }

    /**
     * Encode a string with URL-safe Base64.
     *
     * @param string $input The string you want encoded
     *
     * @return string The base64 encode of what you passed in
     */
    public static function urlsafeB64Encode(string $input): string
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    /**
     * Get the number of bytes in cryptographic strings.
     *
     * @param string $str
     * @return int
     */
    private static function safeStrlen(string $str): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, '8bit');
        }
        return strlen($str);
    }
}
