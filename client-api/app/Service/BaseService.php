<?php

namespace App\Services;

/**
 * service 基类
 */
class BaseService{

    private static $obj_error;

    /**
     * 添加错误
     *
     * @param [type] $msg
     * @param [type] $code
     * @return boolean
     */
    protected static function addError($msg, $code)
    {
        if (!self::$obj_error) {
            self::$obj_error = new class{};
        }
        self::$obj_error->message = $msg;
        self::$obj_error->code = $code;
        return true;
    }

    /**
     * 获取错误信息
     *
     * @return string | boolean
     */
    public static function getLastError()
    {
        if (self::$obj_error) {
            return self::$obj_error->message;
        }
        return '未知错误';
    }

    /**
     * 获取错误码
     *
     * @return int | boolean
     */
    public static function getLastErrorCode(){
        if (self::$obj_error) {
            return self::$obj_error->code;
        }
        return '500';
    }


}