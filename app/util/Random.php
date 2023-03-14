<?php

namespace app\util;

/**
 * 随机文本工具类
 */

class Random
{
    static public function String($l)
    {
        $return = '';
        $sample = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $length = strlen($sample);
        for ($i = 0; $i < $l; $i++) {
            $return .= $sample[mt_rand(0, $length - 1)];
        }
        return $return;
    }

    static public function Uuid()
    {
        $md5 = md5(uniqid());
        return substr($md5, 0, 8) . '-' . substr($md5, 8, 4) . '-' . substr($md5, 12, 4) . '-' . substr($md5, 16, 4) . '-' . substr($md5, 20, 12);
    }
}
