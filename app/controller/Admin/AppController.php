<?php

namespace app\controller\Admin;

use app\class\Request;
use app\model\App;
use app\model\AppVersion;
use app\model\Game;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AppController
{
    static public $rules = [
        'id'                => 'nullable|integer',
        'game_id'           => 'required|integer',
        'name'              => 'required',
        'description'       => 'nullable',
        'os'                => 'required|in:linux,windows',
        'data_path'         => 'required|json',
        'working_path'      => 'required',
        'images'            => 'required|json',
        'config'            => 'required',
        'startup'           => 'required',
        'stop'              => 'required',
        'skip_install'      => 'required|boolean',
        'install_image'     => 'required_unless:skip_install,1',
        'install_script'    => 'required_unless:skip_install,1'
    ];

    public function GetList(Request $request)
    {
        return json([
            'code' => 200,
            'data' => App::select(['id', 'game_id', 'name', 'updated_at', 'created_at'])
                ->withCount('instances')
                ->withCount('versions')
                ->with('game:id,name')
                ->get()
        ]);
    }

    public function Create(Request $request)
    {
        try {
            $data = $request->validate(self::$rules);
            Game::findOrFail($data['game_id']);
            App::create($data);

            return json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            return json(['code' => 400, 'msg' => '游戏不存在。'])->withStatus(400);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }

    public function GetDetail(Request $request, Int $appId)
    {
        try {
            return json([
                'code' => 200,
                'attributes' => App::findOrFail($appId)
            ]);
        } catch (ModelNotFoundException $e) {
            return json(['code' => 400, 'msg' => '镜像不存在。'])->withStatus(400);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }

    public function Import(Request $request)
    {
        try {
            $data = $request->validate([
                'game_id' => 'required|integer',
                'content' => 'required|json'
            ]);
            $content = json_decode($data['content'], true);

            Game::findOrFail($data['game_id']);
            $app = App::create(['game_id' => $data['game_id']] + $content);
            foreach ($content['versions'] as $version)
                AppVersion::create(['app_id' => $app->id] + $version);

            return json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            return json(['code' => 400, 'msg' => '游戏不存在。'])->withStatus(400);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }

    public function Export(Request $request, int $appId)
    {
        try {
            $app = App::with(['versions'])
                ->findOrFail($appId)
                ->makeHidden('id', 'game_id', 'created_at', 'updated_at');
            $app->versions->each->makeHidden('id', 'app_id', 'created_at', 'updated_at');
            return json([
                'code' => 200,
                'attributes' => [
                    'content' => $app->toJson()
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return json(['code' => 400, 'msg' => '镜像不存在。'])->withStatus(400);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }

    public function GetVersions(Request $request, int $appId)
    {
        return json([
            'code' => 200,
            'data' => App::with(['versions'])->find($appId)->versions->makeHidden(['node_id', 'updated_at', 'created_at'])
        ]);
    }

    public function Update(Request $request, int $appId)
    {
        try {
            $data = $request->validate(self::$rules);
            Game::findOrFail($data['game_id']);
            App::findOrFail($appId)->fill($data)->save();

            return json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            return json(['code' => 400, 'msg' => '游戏或镜像不存在。'])->withStatus(400);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }

    public function Delete(Request $request, int $appId)
    {
        try {
            $app = App::with(['versions'])->withCount(['instances'])->findOrFail($appId);
            if ($app->instances_count > 0) throw new \Exception('无法删除带有实例的镜像。', 400);
            $app->versions->each->delete();
            $app->delete();

            return json(['code' => 200]);
        } catch (ModelNotFoundException $e) {
            return json(['code' => 400, 'msg' => '镜像不存在。'])->withStatus(400);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }
}
