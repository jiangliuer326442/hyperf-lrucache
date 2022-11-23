<?php

declare(strict_types=1);

namespace Mustafa\Lrucache\Core;

use Mustafa\Lrucache\SwooleTableManage;
use Hyperf\Di\Annotation\Inject;

class HashList
{
    #[Inject]
    private RNCacheInterface $RNCache;

    private ?Node $head;

    private ?Node $tail;

    private string $table;

    public function __construct(string $table, Node $head = null, Node $tail = null)
    {
        $this->head = $head;
        $this->tail = $tail;
        $this->table = $table;
    }

    public function getSize(): int
    {
        return count(SwooleTableManage::instance($this->table));
    }

    /**
     * @param string $key
     * @return bool
     *              检查键是否存在
     */
    public function checkIndex(string $key): bool
    {
        return SwooleTableManage::instance($this->table)->exist($key);
    }

    /**
     * @param $key
     * @return array
     *               获取key的值
     */
    public function get($key): array
    {
        $res = SwooleTableManage::instance($this->table)->get($key);

        if ($res === false) {
            $ret = $this->RNCache->get($key);
            if (!$ret) return [];
            [$val, $expireAt] = $ret;
            if ($expireAt > time()) {
                $this->addAsHead($key, $val, $expireAt - time());
                return $val;
            }
            return [];
        }
        $_pre = $res['_pre'];
        $_next = $res['_next'];
        unset($res['_pre'], $res['_next']);

        $node = new Node($res);
        $node->setKey($key);
        $node->setPre($_pre);
        $node->setNext($_next);
        $node->setExpireAt($res['_expire']);
        $this->moveToHead($node);
        $ret = [];
        foreach($node->getVal() as $_key => $_val){
            if (!str_starts_with($_key, '_')){
                $ret[$_key] = $_val;
            }
        }
        return $ret;
    }

    /**
     * @param $key
     * @param $val
     * 新加入的节点
     */
    public function addAsHead(string $key, array $val, int $expire): void
    {
        $node = new Node($val, $expire);
        if ($this->tail == null && $this->head != null) {
            $this->tail = $this->head;
            $this->tail->setNext('');
            $this->tail->setPre($key);
            $this->tail->save($this->table);
        }
        $node->setPre('');
        if ($this->head instanceof Node) {
            $node->setNext($this->head->getKey());
        } else {
            $node->setNext('');
        }
        $node->setKey($key);
        $node->save($this->table);
        if ($this->head instanceof Node) {
            $this->head->setPre($key);
            $this->head->save($this->table);
        }
        $this->head = $node;
    }

    /**
     * @param $key
     * 移除指针(删除最近最少使用原则)
     */
    public function removeNode($key)
    {
        $currentTimestamp = time();
        $current = $this->head;
        for ($i = 1; $i < $this->getSize(); ++$i) {
            if ($current->getKey() == $key) {
                $this->RNCache->del($current->getKey());
                break;
            }
            if ($current->getExpireAt() < $currentTimestamp) {
                $this->RNCache->del($current->getKey());
                break;
            }

            $next_key = $current->getNext();
            if (!$next_key){
                break;
            }
            $res = SwooleTableManage::instance($this->table)->get($next_key);
            if (!$res){
                break;
            }
            $current = new Node($res);
            $current->setKey($next_key);
            $current->setPre($res['_pre']);
            $current->setExpireAt($res['_expire']);
            $current->setNext($res['_next']);
        }
        SwooleTableManage::instance($this->table)->del($current->getKey());
        // 调整指针
        if (!$current->getPre()) {
            $next_key = $current->getNext();
            $res = SwooleTableManage::instance($this->table)->get($next_key);
            if ($res) {
                $current = new Node($res);
                $current->setKey($next_key);
                $current->setPre('');
                $current->setNext($res['_next']);
                $current->setExpireAt($res['_expire']);
                $current->save($this->table);
                $this->head = $current;
            }else{
                $this->head = null;
            }
        } elseif (!$current->getNext()) {
            $pre_key = $current->getPre();
            $res = SwooleTableManage::instance($this->table)->get($pre_key);
            if ($res) {
                $current = new Node($res);
                $current->setKey($pre_key);
                $current->setPre($res['_pre']);
                $current->setNext('');
                $current->setExpireAt($res['_expire']);
                $current->save($this->table);
                $this->tail = $current;
            }else{
                $this->tail = null;
            }
        } else {
            $next_key = $current->getNext();
            $res = SwooleTableManage::instance($this->table)->get($next_key);
            $current1 = new Node($res);
            $current1->setKey($next_key);
            $current1->setPre('');
            $current1->setNext($res['_next']);
            $current1->setExpireAt($res['_expire']);
            $current1->save($this->table);
            $pre_key = $current->getPre();
            $res = SwooleTableManage::instance($this->table)->get($pre_key);
            $current2 = new Node($res);
            $current2->setKey($pre_key);
            $current2->setPre($res['_pre']);
            $current2->setNext('');
            $current2->setExpireAt($res['_expire']);
            $current2->save($this->table);
        }
    }

    /**
     * @param Node $node
     *                   把对应的节点应到链表头部(最近get或者刚刚put进去的node节点)
     */
    public function moveToHead(Node $node)
    {
        if ($node->getKey() == $this->head->getKey()) {
            return;
        }

        $origin_pre = $node->getPre();
        $origin_next = $node->getNext();

        if ($origin_pre) {
            $res = SwooleTableManage::instance($this->table)->get($origin_pre);
            unset($res['_next']);
            $pre_node = new Node($res);
            $pre_node->setKey($node->getPre());
            $pre_node->setPre($res['_pre']);
            $pre_node->setNext($origin_next);
            $pre_node->setExpireAt($res['_expire']);
            $pre_node->save($this->table);
        }

        if ($origin_next) {
            $res = SwooleTableManage::instance($this->table)->get($origin_next);
            unset($res['_pre']);
            $next_node = new Node($res);
            $next_node->setKey($node->getNext());
            $next_node->setPre($origin_pre);
            $next_node->setNext($res['_next']);
            $next_node->setExpireAt($res['_expire']);
            $next_node->save($this->table);
        }
        $node->setNext($this->head->getKey());
        $node->setPre('');
        $node->save($this->table);

        $this->head->setPre($node->getKey());
        if ($this->head->getNext() === $node->getKey()) {
            $this->head->setNext($origin_next);
        }
        $this->head->save($this->table);

        $this->head = $node;
    }
}
