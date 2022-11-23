<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Core;

use Mustafa\Lrucache\SwooleTableManage;

class Node
{
    private string $key = '';

    private array $val = [];

    private string $next = '';

    private string $pre = '';

    private int $expireAt = -1;

    public function __construct(array $val, int $expire = -1)
    {
        $this->val = $val;
        if ($expire > 0) {
            $this->expireAt = time() + $expire;
        }
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $key
     */
    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    /**
     * @return string
     */
    public function getNext(): string
    {
        return $this->next;
    }

    /**
     * @param string $next
     */
    public function setNext(string $next): void
    {
        $this->next = $next;
    }

    /**
     * @return string
     */
    public function getPre(): string
    {
        return $this->pre;
    }

    /**
     * @param string $pre
     */
    public function setPre(string $pre): void
    {
        $this->pre = $pre;
    }

    public function save(string $table): void
    {
        SwooleTableManage::instance($table)->set($this->getKey(), array_merge($this->val, [
            '_pre' => $this->getPre(),
            '_next' => $this->getNext(),
            '_expire' => $this->getExpireAt(),
        ]));
    }

    /**
     * @return array
     */
    public function getVal(): array
    {
        return $this->val;
    }

    /**
     * @return int
     */
    public function getExpireAt(): int
    {
        return $this->expireAt;
    }

    /**
     * @param int $expireAt
     */
    public function setExpireAt(int $expireAt): void
    {
        $this->expireAt = $expireAt;
    }
}
