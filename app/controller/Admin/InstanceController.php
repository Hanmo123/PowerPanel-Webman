<?php

namespace app\controller\Admin;

use app\class\Request;
use app\model\App;
use app\model\AppVersion;
use app\model\Instance;
use app\model\InstanceRelationship;
use app\model\Node;
use app\model\NodeAllocation;
use app\model\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InstanceController
{
    static public $rules = [
        'id'                    => 'nullable|integer',
        'name'                  => 'required',
        'description'           => 'nullable',
        'is_suspended'          => 'boolean',
        'relationship.user_id'  => 'required|integer',
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

            $node = Node::findOrFail($data['node_id']);
            $allocation = NodeAllocation::whereNull('ins_id')->findOrFail($data['node_allocation_id']);
            $app = App::findOrFail($data['app_id']);
            $user = User::findOrFail($data['relationship']['user_id']);
            AppVersion::where('app_id', $app->id)->findOrFail($data['app_version_id']);

            if ($app->os != $node->os)
                throw new \Exception('节点操作系统与应用不匹配。');

            $ins = new Instance($data);
            $ins->genUuid();
            $ins->save();

            $allocation->ins_id = $ins->id;
            $allocation->save();

            InstanceRelationship::create([
                'user_id' => $user->id,
                'ins_id' => $ins->id,
                'is_owner' => 1,
                'permission' => json_encode(['all'])
            ]);

            $ins->reinstall();

            return json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            return json(['code' => 400, 'msg' => '节点、应用、端口、应用版本不存在或端口不可用。'])->withStatus(400);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }

    public function GetDetail(Request $request, int $insId)
    {
        return json([
            'code' => 200,
            'attributes' => Instance::with(['relationship'])->find($insId)
        ]);
    }

    public function Update(Request $request, int $appId)
    {
        try {
            $data = $request->validate(self::$rules);

            // 获取实例模型
            $ins = Instance::with(['relationship'])->findOrFail($data['id']);

            // 更新实例所有关系
            if ($data['relationship']['user_id'] != $ins->relationship->user_id) { // 需要更新
                // 检查用户是否存在
                User::findOrFail($data['relationship']['user_id']);
                $ins->relationship->user_id = $data['relationship']['user_id'];
                $ins->relationship->save();
            }

            // 更新实例端口
            if ($data['node_allocation_id'] != $ins->node_allocation_id) {  // 需要更新
                $target = NodeAllocation::whereNull('ins_id')->findOrFail($data['node_allocation_id']);
                $target->ins_id = $ins->id;
                $target->save();

                $current = NodeAllocation::findOrFail($ins->node_allocation_id);
                $current->ins_id = null;
                $current->save();

                $ins->node_allocation_id = $target->id;
            }

            // 更新实例信息
            unset($data['relationship']);
            $ins->fill($data)->save();

            return json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            return json(['code' => 400, 'msg' => '实例、用户或可用端口不存在。'])->withStatus(400);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }
}
