<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use support\Model;

class User extends Model
{
    protected $table = 'user';
    protected $fillable = ['name', 'email', 'is_admin'];
    protected $guarded = ['password'];
    public $timestamps = true;

    public function instances(): HasManyThrough
    {
        return $this->hasManyThrough(Instance::class, InstanceRelationship::class, 'user_id', 'id', 'id', 'ins_id');
    }

    public function permission()
    {
        return $this->hasOne(UserPermission::class, 'user_id', 'id');
    }

    public function passwd(string $password)
    {
        $this->password = hash('sha512', $password . getenv('APP_SALT'));
    }

    static public function wherePassword(string $password)
    {
        return self::where('password', hash('sha512', $password . getenv('APP_SALT')));
    }

    static public function HandleCreate(array $attributes, string $password)
    {
        // 创建用户
        $user = new self($attributes);
        $user->passwd($password);
        $user->save();

        // 写入用户默认权限
        UserPermission::create([
            'user_id' => $user->id,
            'permission' => json_encode($attributes['is_admin'] ? ['all', 'admin.all'] : ['all'])
        ]);
    }

    public function handleDelete()
    {
        InstanceRelationship::where('user_id', $this->id)->delete();    // 删除用户的实例关系
        UserPermission::where('user_id', $this->id)->delete();          // 删除用户权限
        ApiKey::where('user_id', $this->id)->delete();                  // 删除用户 API 密钥

        // 删除用户
        $this->delete();
    }
}
