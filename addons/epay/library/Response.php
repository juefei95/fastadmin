<?php

namespace addons\epay\library;

class Response extends \Symfony\Component\HttpFoundation\Response implements \JsonSerializable, \Serializable
{
    public function __toString()
    {
        return $this->getContent();
    }

    public function jsonSerialize(): mixed
    {
        return $this->getContent();
    }

    public function serialize()
    {
        return serialize($this->content);
    }

    public function unserialize($data)
    {
        return $this->content = unserialize($data);
    }

    /**
     * (PHP 8.1+) Magic method for serialization.
     *
     * @return mixed
     */
    public function __serialize()
    {
        return $this->content;
    }

    /**
     * (PHP 8.1+) Magic method for unserialization.
     *
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->content = $data;
    }
}
