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

use Jose\Object\JWEInterface;
use Jose\Object\JWKInterface;
use Jose\Object\JWKSetInterface;
use Psr\Log\LoggerInterface;

/**
 * Decrypter Interface.
 */
interface DecrypterInterface
{
    /**
     * @param string[]|\Jose\Algorithm\KeyEncryptionAlgorithmInterface[]     $key_encryption_algorithms
     * @param string[]|\Jose\Algorithm\ContentEncryptionAlgorithmInterface[] $content_encryption_algorithms
     * @param string[]|\Jose\Compression\CompressionInterface[]              $compression_methods
     * @param \Psr\Log\LoggerInterface|null                                  $logger
     *
     * @return \Jose\DecrypterInterface
     */
    public static function createDecrypter(array $key_encryption_algorithms, array $content_encryption_algorithms, array $compression_methods = ['DEF', 'ZLIB', 'GZ'], LoggerInterface $logger = null);

    /**
     * @return string[]
     */
    public function getSupportedKeyEncryptionAlgorithms();

    /**
     * @return string[]
     */
    public function getSupportedContentEncryptionAlgorithms();

    /**
     * @return string[]
     */
    public function getSupportedCompressionMethods();

    /**
     * @param \Jose\Object\JWEInterface $input           A JWE object to decrypt
     * @param \Jose\Object\JWKInterface $jwk             The key used to decrypt the input
     * @param null|int                  $recipient_index If the JWE has been decrypted, an integer that represents the ID of the recipient is set
     */
    public function decryptUsingKey(JWEInterface &$input, JWKInterface $jwk, &$recipient_index = null);

    /**
     * @param \Jose\Object\JWEInterface    $input           A JWE object to decrypt
     * @param \Jose\Object\JWKSetInterface $jwk_set         The key set used to decrypt the input
     * @param null|int                     $recipient_index If the JWE has been decrypted, an integer that represents the ID of the recipient is set
     */
    public function decryptUsingKeySet(JWEInterface &$input, JWKSetInterface $jwk_set, &$recipient_index = null);
}
