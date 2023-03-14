<?php

namespace app\controller\Admin;

use app\class\Request;
use app\model\App;
use app\model\AppVersion;
use app\model\Instance;
use app\model\InstanceRelationship;
use app\model\Node;
use app\model\NodeAllocation;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InstanceController
{
    static public $rules = [
        'id'                    => 'nullable|integer',
        'name'                  => 'required',
        'description'           => 'nullable',
        'is_suspended'          => 'boolean',
        'node_id'               => 'required|integer',
        'node_allocation_id'    => 'required|integer',
        'app_id'                => 'required|integer',
        'app_version_id'        => 'required|integer',
        'cpu'                   => 'required|integer',
        'memory'                => 'required|integer',
        'swap'                  => 'required|integer',
        'disk'                  => 'required|integer',
        'image'                 => 'required'
    ];

    public function GetList(Request $request)
    {
        return json([
            'code' => 200,
            'data' => Instance::with([
                'relationship' => fn ($query) => $query->select(['ins_id', 'user_id'])
                    ->with(['user:id,name'])
                    ->where('is_owner', 1),
                'node:id,name',
                'stats:ins_id,status',
                'app:id,name',
                'version:id,name'
            ])->get(['id', 'name', 'node_id', 'app_id', 'app_version_id', 'created_at'])
        ]);
    }

    public function Create(Request $request)
    {
        try {
            $data = $request->validate(self::$rules);

            Node::findOrFail($data['node_id']);
            $allocation = NodeAllocation::whereNull('ins_id')->findOrFail($data['node_allocation_id']);
            $app = App::findOrFail($data['app_id']);
            AppVersion::where('app_id', $app->id)->findOrFail($data['app_version_id']);

            // TODO 检查 OS 是否一致

            $ins = new Instance($data);
            $ins->genUuid();
            $ins->save();

            $allocation->ins_id = $ins->id;
            $allocation->save();

            InstanceRelationship::create([
                'user_id' => 1,
                'ins_id' => $ins->id,
                'is_owner' => 1,
                'permission' => json_encode(['all'])
            ]);

            // TODO 通知节点创建实例

            return json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            return json(['code' => 400, 'msg' => '节点、应用、可用端口、应用版本不存在。'])->withStatus(400);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }

    public function GetDetail(Request $request, int $insId)
    {
        return json([
            'code' => 200,
            'attributes' => Instance::find($insId)
        ]);
    }
}
