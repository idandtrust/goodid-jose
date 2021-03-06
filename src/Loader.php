<?php

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2016 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Jose;

use Assert\Assertion;
use Jose\Behaviour\HasLogger;
use Jose\Object\JWEInterface;
use Jose\Object\JWKInterface;
use Jose\Object\JWKSet;
use Jose\Object\JWKSetInterface;
use Jose\Object\JWSInterface;
use Jose\Util\JWELoader;
use Jose\Util\JWSLoader;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class able to load JWS or JWE.
 * JWS object can also be verified.
 */
final class Loader implements LoaderInterface
{
    use HasLogger;

    /**
     * {@inheritdoc}
     */
    public function loadAndDecryptUsingKey($input, JWKInterface $jwk, array $allowed_key_encryption_algorithms, array $allowed_content_encryption_algorithms, &$recipient_index = null, LoggerInterface $logger = null)
    {
        $jwk_set = new JWKSet();
        $jwk_set->addKey($jwk);

        return $this->loadAndDecrypt($input, $jwk_set, $allowed_key_encryption_algorithms, $allowed_content_encryption_algorithms, $recipient_index, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function loadAndDecryptUsingKeySet($input, JWKSetInterface $jwk_set, array $allowed_key_encryption_algorithms, array $allowed_content_encryption_algorithms, &$recipient_index = null, LoggerInterface $logger = null)
    {
        return $this->loadAndDecrypt($input, $jwk_set, $allowed_key_encryption_algorithms, $allowed_content_encryption_algorithms, $recipient_index, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function loadAndVerifySignatureUsingKey($input, JWKInterface $jwk, array $allowed_algorithms, &$signature_index = null, LoggerInterface $logger = null)
    {
        $jwk_set = new JWKSet();
        $jwk_set->addKey($jwk);

        return $this->loadAndVerifySignature($input, $jwk_set, $allowed_algorithms, null, $signature_index, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function loadAndVerifySignatureUsingKeySet($input, JWKSetInterface $jwk_set, array $allowed_algorithms, &$signature_index = null, LoggerInterface $logger = null)
    {
        return $this->loadAndVerifySignature($input, $jwk_set, $allowed_algorithms, null, $signature_index, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function loadAndVerifySignatureUsingKeyAndDetachedPayload($input, JWKInterface $jwk, array $allowed_algorithms, $detached_payload, &$signature_index = null, LoggerInterface $logger = null)
    {
        $jwk_set = new JWKSet();
        $jwk_set->addKey($jwk);

        return $this->loadAndVerifySignature($input, $jwk_set, $allowed_algorithms, $detached_payload, $signature_index, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function loadAndVerifySignatureUsingKeySetAndDetachedPayload($input, JWKSetInterface $jwk_set, array $allowed_algorithms, $detached_payload, &$signature_index = null, LoggerInterface $logger = null)
    {
        return $this->loadAndVerifySignature($input, $jwk_set, $allowed_algorithms, $detached_payload, $signature_index, $logger);
    }

    /**
     * @param string                        $input
     * @param \Jose\Object\JWKSetInterface  $jwk_set
     * @param array                         $allowed_key_encryption_algorithms
     * @param array                         $allowed_content_encryption_algorithms
     * @param null|int                      $recipient_index
     * @param \Psr\Log\LoggerInterface|null $logger
     *
     * @return \Jose\Object\JWEInterface
     */
    private function loadAndDecrypt($input, JWKSetInterface $jwk_set, array $allowed_key_encryption_algorithms, array $allowed_content_encryption_algorithms, &$recipient_index = null, LoggerInterface $logger = null)
    {
        $jwt = $this->load($input);
        Assertion::isInstanceOf($jwt, JWEInterface::class, 'The input is not a valid JWE');
        $decrypted = Decrypter::createDecrypter($allowed_key_encryption_algorithms, $allowed_content_encryption_algorithms, ['DEF', 'ZLIB', 'GZ'], $logger);

        $decrypted->decryptUsingKeySet($jwt, $jwk_set, $recipient_index);

        return $jwt;
    }

    /**
     * @param string                        $input
     * @param \Jose\Object\JWKSetInterface  $jwk_set
     * @param array                         $allowed_algorithms
     * @param string|null                   $detached_payload
     * @param null|int                      $signature_index
     * @param \Psr\Log\LoggerInterface|null $logger
     *
     * @return \Jose\Object\JWSInterface
     */
    private function loadAndVerifySignature($input, JWKSetInterface $jwk_set, array $allowed_algorithms, $detached_payload = null, &$signature_index = null, LoggerInterface $logger = null)
    {
        $jwt = $this->load($input);
        Assertion::isInstanceOf($jwt, JWSInterface::class, 'The input is not a valid JWS.');
        $verifier = Verifier::createVerifier($allowed_algorithms, $logger);

        $verifier->verifyWithKeySet($jwt, $jwk_set, $detached_payload, $signature_index);

        return $jwt;
    }

    /**
     * {@inheritdoc}
     */
    public function load($input)
    {
        $this->log(LogLevel::INFO, 'Trying to load the input.', ['input' => $input]);
        $json = $this->convert($input);
        if (array_key_exists('signatures', $json)) {
            $this->log(LogLevel::DEBUG, 'The input is a JWS.', ['json' => $json]);

            return JWSLoader::loadSerializedJsonJWS($json);
        }
        if (array_key_exists('recipients', $json)) {
            $this->log(LogLevel::DEBUG, 'The input is a JWE.', ['json' => $json]);

            return JWELoader::loadSerializedJsonJWE($json);
        }
    }

    /**
     * @param string $input
     *
     * @return array
     */
    private function convert($input)
    {
        if (is_array($data = json_decode($input, true))) {
            if (array_key_exists('signatures', $data) || array_key_exists('recipients', $data)) {
                $this->log(LogLevel::DEBUG, 'The input seems to be a JWS or a JWE.');

                return $data;
            } elseif (array_key_exists('signature', $data)) {
                $this->log(LogLevel::DEBUG, 'The input seems to be a flattened JWS.');

                return $this->fromFlattenedSerializationSignatureToSerialization($data);
            } elseif (array_key_exists('ciphertext', $data)) {
                $this->log(LogLevel::DEBUG, 'The input seems to be a flattened JWE.');

                return $this->fromFlattenedSerializationRecipientToSerialization($data);
            }
        } elseif (is_string($input)) {
            $this->log(LogLevel::DEBUG, 'The input may be a compact JWS or JWE.');

            return $this->fromCompactSerializationToSerialization($input);
        }
        $this->log(LogLevel::ERROR, 'The input is not supported');
        throw new \InvalidArgumentException('Unsupported input');
    }

    /**
     * @param $input
     *
     * @return array
     */
    private function fromFlattenedSerializationRecipientToSerialization($input)
    {
        $recipient = [];
        foreach (['header', 'encrypted_key'] as $key) {
            if (array_key_exists($key, $input)) {
                $recipient[$key] = $input[$key];
            }
        }
        $recipients = [
            'ciphertext' => $input['ciphertext'],
            'recipients' => [$recipient],
        ];
        foreach (['protected', 'unprotected', 'iv', 'aad', 'tag'] as $key) {
            if (array_key_exists($key, $input)) {
                $recipients[$key] = $input[$key];
            }
        }

        $this->log(LogLevel::INFO, 'JWE in Flattened JSON Serialization mode loaded.');

        return $recipients;
    }

    /**
     * @param $input
     *
     * @return array
     */
    private function fromFlattenedSerializationSignatureToSerialization($input)
    {
        $signature = [
            'signature' => $input['signature'],
        ];
        foreach (['protected', 'header'] as $key) {
            if (array_key_exists($key, $input)) {
                $signature[$key] = $input[$key];
            }
        }

        $temp = [];
        if (!empty($input['payload'])) {
            $temp['payload'] = $input['payload'];
        }
        $temp['signatures'] = [$signature];

        $this->log(LogLevel::INFO, 'JWS in Flattened JSON Serialization mode loaded.');

        return $temp;
    }

    /**
     * @param string $input
     *
     * @return array
     */
    private function fromCompactSerializationToSerialization($input)
    {
        $parts = explode('.', $input);
        switch (count($parts)) {
            case 3:
                $this->log(LogLevel::DEBUG, 'The input seems to be a compact JWS.');

                return $this->fromCompactSerializationSignatureToSerialization($parts);
            case 5:
                $this->log(LogLevel::DEBUG, 'The input seems to be a compact JWE.');

                return $this->fromCompactSerializationRecipientToSerialization($parts);
            default:
                throw new \InvalidArgumentException('Unsupported input');
        }
    }

    /**
     * @param array $parts
     *
     * @return array
     */
    private function fromCompactSerializationRecipientToSerialization(array $parts)
    {
        $recipient = [];
        if (!empty($parts[1])) {
            $recipient['encrypted_key'] = $parts[1];
        }

        $recipients = [
            'recipients' => [$recipient],
        ];
        foreach ([0 => 'protected', 2 => 'iv', 3 => 'ciphertext', 4 => 'tag'] as $part => $key) {
            if (!empty($parts[$part])) {
                $recipients[$key] = $parts[$part];
            }
        }

        $this->log(LogLevel::INFO, 'JWE in Compact JSON Serialization mode loaded.');

        return $recipients;
    }

    /**
     * @param array $parts
     *
     * @return array
     */
    private function fromCompactSerializationSignatureToSerialization(array $parts)
    {
        $temp = [];

        if (!empty($parts[1])) {
            $temp['payload'] = $parts[1];
        }
        $temp['signatures'] = [[
            'protected' => $parts[0],
            'signature' => $parts[2],
        ]];

        $this->log(LogLevel::INFO, 'JWS in Compact JSON Serialization mode loaded.');

        return $temp;
    }
}
