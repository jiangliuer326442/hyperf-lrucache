# hyperf lrucache

基于swoole hyperf框架开发的，支持从 数据库 - redis - swoole table 三级缓存，支持给数据设置过期时间。

swoole table 通过lru淘汰算法清理早期插入的数据，通过限制swoole table的数据量，控制本地内存缓存使用量。

redis缓存使用 hash+zset 的组合存储数据，支持根据过期时间自动清理数据。

redis缓存更新通过监听数据库的修改和删除事件完成，在多实例swoole table缓存的更新则依赖redis的pub/sub机制

插件提供对swoole table的使用进行监控，以及lrucache 三级缓存的命中情况的监控

## 监控

```prometheus
# HELP beego_learning_swoole_table_hit_db count swoole table hit db
# TYPE beego_learning_swoole_table_hit_db counter
beego_learning_swoole_table_hit_db{table="role"} 1
beego_learning_swoole_table_hit_db{table="user"} 5
# HELP beego_learning_swoole_table_hit_lru count swoole table hit lru
# TYPE beego_learning_swoole_table_hit_lru counter
beego_learning_swoole_table_hit_lru{table="role"} 9
beego_learning_swoole_table_hit_lru{table="user"} 5
# HELP beego_learning_swoole_table_hit_redis count swoole table hit redis
# TYPE beego_learning_swoole_table_hit_redis counter
beego_learning_swoole_table_hit_redis{table="user"} 5
# HELP beego_learning_swoole_table_length gauge swoole table length
# TYPE beego_learning_swoole_table_length gauge
beego_learning_swoole_table_length{table="role"} 1
beego_learning_swoole_table_length{table="user"} 5
# HELP beego_learning_swoole_table_lru_length gauge swoole table lru length
# TYPE beego_learning_swoole_table_lru_length gauge
beego_learning_swoole_table_lru_length{table="role"} 5
beego_learning_swoole_table_lru_length{table="user"} 5
# HELP beego_learning_swoole_table_max_length gauge swoole table max length
# TYPE beego_learning_swoole_table_max_length gauge
beego_learning_swoole_table_max_length{table="role"} 64
beego_learning_swoole_table_max_length{table="user"} 64
# HELP beego_learning_swoole_table_size gauge swoole table size
# TYPE beego_learning_swoole_table_size gauge
beego_learning_swoole_table_size{table="role"} 142816
beego_learning_swoole_table_size{table="user"} 242832
```

* **swoole_table_hit_lru** 缓存 swoole table 命中次数
* **swoole_table_hit_redis** redis缓存命中次数
* **swoole_table_hit_db** 缓存命中失败，查询db次数
* **swoole_table_length** swoole table 实际存放数据的数量
* **swoole_table_lru_length** swoole table lru淘汰算法配置的最大数量
* **swoole_table_max_length*** swoole table 的最大容量
* **swoole_table_size** swoole table占用的内存 bit

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

return [
    'redis' => [
        'prefix' => 'swooletable:cache:', // redis key前缀
        'pool' => 'default', // 使用哪个redis连接池做缓存
        'hash_key_length' => 3, // 使用hash做缓存key的默认长度
        'expire' => 86400, //缓存默认过期时间
    ],
];
```

### 使用

在hyperf启动时创建swoole table，因此需要在代码中定义好swoole table的字段、表名、最大容量（最大容量只能是2的N次方）、lru淘汰算法的触发数量，示例代码如下

```php
/**
 * @property int $id
 * @property string $name 权限名称
 * @property string $status 状态:enabled 启用;disabled 禁用;
 * @property array $data 权限配置
 * @property Carbon $created_time 创建时间
 * @property Carbon $updated_time 更新时间
 * @property Carbon $deleted_time 删除时间
 * @method void setName(string $field)
 * @method void setStatus(string $field)
 * @method void setData(array $field)
 */
#[SwooleTable(swooleTableSize: 8, lruLimit: 5, hashKeyLength: -1)]
class RoleDaoImpl extends \Hyperf\DbConnection\Model\Model
{
    use SoftDeletes;
    use Snowflake;

    protected ?string $table = 'role';

    protected array $fillable = [
        'id', 'name', 'status', 'data',
    ];

    protected array $casts = [
        'id' => 'integer',
        'data' => 'array',
        'created_time' => 'datetime',
        'updated_time' => 'datetime',
        'deleted_time' => 'datetime',
    ];

    public function getField(): array
    {
        return [];
    }

    #[SwooleTableCache]
    public function getById(int $id): ?RoleDaoImpl
    {
        return self::query()->find($id);
    }
}

```

RoleDaoImpl 是基于 hyperf规则，针对 数据库 `role` 映射出来的 ORM 模型，lrucache针对这个模型，有如下额外要求：

1. 类名上有例如 `@property string $name` 这样的 php doc注释，这样 swoole table 可以根据这些创建表字段
2. 类名上面有 `#[SwooleTable(swooleTableSize: 8, lruLimit: 5, hashKeyLength: -1)]` 这样的注解，**swooleTableSize** 是 swoole table 表创建时指定的最大size， **lruLimit** 时lru淘汰算法控制的链表最大长度，**hashKeyLength** 是对缓存key切割成hash的长度，-1 代表所有的缓存key共用一个hash
3. 带有 `SwooleTableCache` 注解的 **getById** 函数，根据主键查询，返回模型的对象。使用该注解的查询会通过swoole table - redis - db 的顺序进行数据寻找。注解有 `expire` 参数，代码缓存设置的过期时间，没有配置该项，表明使用config配置的通用过期时间，也就是上面设置的一天。

## 进阶玩法

可以手动操作缓存
### 直接操作 swoole table + redis 缓存

存入数据，字段为前缀，key，数据数组，过期时间，会把数据放入lru算法的swoole table，以及 hash+zset 的 redis中

```php
$cache = LRUCacheManager::instance('test_table');
$cache->put('test_table:', "8555201", [
	'id' => rand(1, 99),
	'name' => 'fang',
	'age' => 23,
], 86400 * 7);
```

读取数据，如果swoole table没有数据，会从redis缓存拿，会校验数据是否过期

```php
$cache->get('test_table:', "8555201")
```

### 直接操作redis缓存

不想使用swoole table缓存？框架支持只把数据缓存到redis，不做swoole table的缓存，方法如下

```php
$RNCache = make(RNCacheInterface::class);

//存储
$RNCache->set('aaaaaa:','12222222', [
	'id' => 12,
	'name' => 'zhongjian',
	'age' => 26,
], 86400 * 12);

//获取
$ret = $RNCache->get('aaaaaa:', '12222222');

//删除
$RNCache->del('aaaaaa:', '12222222');

```

## 联系作者

#### 我的邮箱
- fanghailiang2016@gmail.com
- hailiang_fang@163.com

#### 微信

**fanghailiang2023**