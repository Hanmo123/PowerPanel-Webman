<?php

namespace app\controller;

use app\class\Request;
use app\handler\Instance\TokenHandler;
use support\Response;
use Throwable;

class Instance
{
    public function GetList(Request $request): Response
    {
        return json([
            'code' => 200,
            'data' => $request->getUser()->instances()->with(['stats' => function ($query) {
                $query->select(['ins_id', 'status']);
            }])->get()
        ]);
    }

    public function GetDetail(Request $request): Response
    {
        try {
            return json([
                'code' => 200,
                'attributes' => $request->getInstance()->load('allocation')
            ]);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }

    public function GetConsole(Request $request): Response
    {
        try {
            $instance = $request->getInstance();

            $relationships = $instance->relationship->checkPermission([
                'console.status.get',
                'console.status.set',
                'console.history',
                'console.read',
                'console.stats',
                'console.write'
            ]);
            if (!$relationships) throw new \Exception('实例权限不足。', 401);

            $token = $instance
                ->getTokenHandler()
                ->generate(TokenHandler::TYPE_WS, $relationships, ['instance' => $instance->uuid]);
            return json([
                'code' => 200,
                'attributes' => [
                    'endpoint' => $token->node->getAddress('ws') . '/ws/console',
                    'token' => $token->token
                ]
            ]);
        } catch (\Throwable $th) {
            return json(['code' => $th->getCode(), 'msg' => $th->getMessage()])->withStatus($th->getCode());
        }
    }

    public function Rename(Request $request)
    {
        try {
            $request->getInstance()->rename($request->post('name'));
            return json(['code' => 200]);
        } catch (Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }

    public function Reinstall(Request $request)
    {
        try {
            $request->getInstance()->reinstall();
            return json(['code' => 200]);
        } catch (Throwable $th) {
            return json(['code' => $th->getCode() ?: 500, 'msg' => $th->getMessage()])->withStatus($th->getCode() ?: 500);
        }
    }
}
