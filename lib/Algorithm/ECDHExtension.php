<?php

namespace SpomkyLabs\JOSE\Algorithm;

use Mdanter\Ecc\PointInterface;

class ECDHExtension
{
    private $generator;
    private $receiverPoint;
    private $senderSecret;
    private $agreed_key;

    public function __construct(PointInterface $generator, $senderSecret)
    {
        $this->generator   = $generator;
        $this->senderSecret = $senderSecret;
    }

    public function setReceiverPoint(PointInterface $receiverPoint)
    {
        $this->receiverPoint = $receiverPoint;
        $this->agreed_key = $this->receiverPoint->mul($this->senderSecret)->getX();
    }

    /**
     * @param string $string
     */
    public function encrypt($string)
    {
        $key = hash("sha256", $this->agreed_key, true);

        return mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $string, MCRYPT_MODE_CBC, $key);
    }

    /**
     * @param string $string
     */
    public function decrypt($string)
    {
        $key = hash("sha256", $this->agreed_key, true);

        return mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $string, MCRYPT_MODE_CBC, $key);
    }
}
