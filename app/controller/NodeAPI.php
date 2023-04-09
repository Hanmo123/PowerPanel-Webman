<?php

namespace app\controller;

use app\model\Instance;
use app\model\InstanceStats;
use app\model\Node;
use support\Db;
use support\Request;
use support\Response;

class NodeAPI
{
    public function GetConfig(Request $request)
    {
        $node = Node::find($request->node->id);
        $addition = json_decode($node->addition, true);

        return json([
            'code' => 200,
            'attributes' => [
                'node_id' => $node->id,
                'node_token' => $node->node_token,
                'node_port' => $node->api_port,
                'docker' => [
                    'socket' => 'unix:///var/run/docker.sock',
                    'dns' => [
                        '114.114.114.114',
                        '119.29.29.29'
                    ]
                ],
                'storage_path' => [
                    'instance_data' => $addition['instance_data_path'],
                    'scripts' => $addition['instance_data_path'] . '/scripts'
                ],
                // TODO TLS 相关设置
                'timezone' => getenv('TIME_ZONE'),
                'max_package_size' => $addition['max_upload_slice_size'] + 2 * 1024 * 1024,
                'max_upload_slice_size' => $addition['max_upload_slice_size'],
                'max_editable_size' => 128 * 1024,
                'report_stats_interval' => 180
            ]
        ]);
    }

    public function GetList(Request $request): Response
    {
        return json([
            'code' => 200,
            'attributes' => [
                'list' => Instance::with(['app', 'version', 'allocation', 'allocations'])->where('node_id', $request->node->id)->get()
            ]
        ]);
    }

    public function UpdateStats(Request $request): Response
    {
        try {
            // 更新数据库数据
            InstanceStats::upsert(
                array_map(function ($data) {
                    return [
                        'ins_id' => $data['id'],
                        'status' => $data['status'],
                        // 'cpu_usage' => $data['resources']['cpu'],        // TODO
                        // 'memory_usage' => $data['resources']['memory'],  // TODO
                        'disk_usage' => $data['resources']['disk'],
                    ];
                }, $request->post()['data']),
                ['ins_id'],
                ['status', 'disk_usage']
            );

            // 返回节点上容量超限的容器列表
            return json([
                'code' => 200,
                'data' => Instance::select('uuid')
                    ->where('node_id', $request->node->id)
                    ->whereRelation('stats', 'disk_usage', '>', Db::raw('`disk` * 1024 * 1024'))
                    ->whereRelation('stats', 'status', InstanceStats::STATUS_RUNNING)
                    ->get()
                    ->mapWithKeys(fn ($item) => [$item->uuid])
                    ->toArray()
            ]);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }

    public function GetDetail(Request $request)
    {
        try {
            return json([
                'code' => 200,
                'attributes' => Instance::with(['allocation', 'allocations', 'app', 'version'])
                    ->where('uuid', $request->post()['attributes']['uuid'])
                    ->first()
            ]);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }
}
