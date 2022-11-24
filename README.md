# hyperf lruncache

基于swoole hyperf框架开发的，支持从 数据库 - redis - swoole table 三级缓存，支持给数据设置过期时间。

swoole table 通过lru淘汰算法清理早期插入的数据，通过限制swoole table的数据量，控制本地内存缓存使用量。
redis缓存并没有使用传统的key-value形式保存数据，原因是大量key value小数据+元数据会占用额外的redis内存空间，同时导致检索key的hash过大，在权衡key-value和bigkey的前提下，使用 hash+zset 的组合存储数据，支持根据过期时间自动清理数据，用lua脚本保证 hash+zset 的原子性。

## 接入

### 安装
```shell
composer require mustafa3264/lrucache
```

### 配置
```shell
php bin/hyperf.php vendor:publish mustafa3264/lrucache
```
配置文件如下
```php
<?php

declare(strict_types=1);

return [
    'redis' => [
        'prefix' => 'swooletable:cache:', // redis key前缀
        'pool' => 'default', // 使用哪个redis连接池做缓存
        'hash_key_length' => 3, // 使用hash做缓存key的长度
    ],
];
```

### 创建swoole table

在hyperf启动时创建swoole table，因此需要在代码中定义好swoole table的字段、表名、最大容量（最大容量只能是2的N次方）、lru淘汰算法的触发数量
```php
<?php

declare(strict_types=1);

namespace App\Model\Lrncache\Entities;

use Mustafa\Lrucache\Annotation\SwooleTable;
use Mustafa\Lrucache\Annotation\SwooleTableItem;
use Mustafa\Lrucache\Entities\BaseTableEntity;

#[SwooleTable(table: 'test_table', swooleTableSize: 10, lruLimit: 5)]
class TestTableEntity extends BaseTableEntity
{
    #[SwooleTableItem(name: 'id', type: \Swoole\Table::TYPE_INT, length: 4)]
    public int $id;

    #[SwooleTableItem(name: 'name', type: \Swoole\Table::TYPE_STRING, length: 64)]
    public int $name;

    #[SwooleTableItem(name: 'age', type: \Swoole\Table::TYPE_INT, length: 3)]
    public int $age;
}

```

## 使用

可以手动操作缓存
### 直接操作 swoole table + redis 缓存

存入数据，字段为前缀，key，数据数组，过期时间

```php
$cache = LRUCacheManager::instance('test_table');
$cache->put('test_table:', 8555201, [
	'id' => rand(1, 99),
	'name' => 'fang',
	'age' => 23,
], 86400 * 7);
```

读取数据，如果swoole table没有数据，会从redis缓存拿，前提是数据没有过期

```php
$cache->get('test_table:', 8555201)
```

### 通过模型数据，自动维护 db - lrucache的缓存

通过在函数名称上面增加注解，拦截方法调用，进行缓存加载、缓存更新或者缓存删除的操作

get方法，返回格式为数组
```php
    #[SwooleTableCache(entity: TestTableEntity::class, expire: 86400 * 7, action: 'get')]
    public function get($id): array
    {
        $ret = TestTable23::query()->find($id);
        if ($ret) {
            return [
                'id' => $ret->getAttribute('id'),
                'name' => $ret->getAttribute('name'),
                'age' => $ret->getAttribute('age'),
            ];
        }
        return [];
    }
```

edit方法，返回数组，修改成功返回发生变动的 key-value 字段
```php
    #[SwooleTableCache(entity: TestTableEntity::class, expire: 86400 * 7, action: 'set')]
    public function edit(int $id, array $datas): array
    {
        $ret = TestTable23::query()->find($id)->update($datas);
        if ($ret) {
            return $datas;
        }
        return [];
    }
```

del方法
```php
#[SwooleTableCache(entity: TestTableEntity::class, expire: 86400 * 7, action: 'del')]
public function del(int $id): bool
{
	return TestTable23::query()->find($id)->delete($id);
}
```

## 番外篇：直接操作redis缓存

框架支持只把数据缓存到redis，不做swoole table的缓存，方法如下

```php
$RNCache = make(RNCacheInterface::class);

//存储
$RNCache->set('aaaaaa:12222222', [
	'id' => 12,
	'name' => 'zhongjian',
	'age' => 26,
], 86400 * 12);

//获取
$ret = $RNCache->get('aaaaaa:12222222');

//删除
$RNCache->del('aaaaaa:12222222');

```

## 联系作者

#### 我的邮箱
- fanghailiang2016@gmail.com
- hailiang_fang@163.com

#### 微信

**fanghailiang2023**