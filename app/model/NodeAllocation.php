<?php

namespace app\model;

use support\Model;

class NodeAllocation extends Model
{
    protected $table = 'node_allocation';
    protected $primaryKey = 'id';
    protected $fillable = ['ip', 'port', 'alias', 'node_id', 'ins_id'];
    public $timestamps = true;

    public function instance()
    {
        return $this->belongsTo(Instance::class, 'ins_id', 'id');
    }
}
