<?php

use Common\Permissions\Facades\Users;

if (!function_exists("checkUserRoleIsRoot")) {

    function checkUserRoleIsRoot(): bool
    {
        $role = auth()->user()->role ?? '';
        if (!$role) {
            return false;
        }

        return in_array('super_admin',$role->pluck('index')->toArray());
    }
}
if (!function_exists("checkUserRole")) {

    function checkUserRole($index = 'super_admin'): bool
    {
        $role = auth()->user()->role ?? '';
        if (!$role) {
            return false;
        }
        $userRoles = $role->pluck('index')->toArray();
        if (is_array($index)){
            foreach ($index as $key){
                if (in_array($key,$userRoles)){
                    return true;
                }
            }
            return false;
        }

        return in_array($index,$role->pluck('index')->toArray());
    }
}


if (!function_exists("getUserRedisInfo")) {
    function getUserRedisInfo($user_id, $field = '')
    {
        $info           = \Common\Permissions\Models\User::query()->select(['id AS user_id', 'name AS user_name', 'email AS user_email', 'mobile', 'openid'])
            ->where('id', $user_id)->where('status', 1)->first();
        $info           = $info ? $info->toArray() : ['user_id'=>'','user_name'=>'','user_email'=>'','mobile'=>'','openid'=>''];
        if (empty($field)) {
            return $info;
        } else {
            return $info[$field] ?? '';
        }
    }
}

/**
 * 只查询在职人员信息
 */
if (!function_exists("getUserOnlineRedisInfo")) {
    function getUserOnlineRedisInfo($user_id, $field = '')
    {
        $user_info = Users::userDetail($user_id,true);
        //email、mobile、unionid、openid(空的可能不存在，慎用)，status
        if(isset($user_info['status']) && $user_info['status'] == 2)
            return null;
        if (empty($field)) {
            return $user_info;
        } else {
            return $user_info[$field] ?? '';
        }
    }
}

if (!function_exists("getRoleUserIds")) {
    function getRoleUserIds($user_id): array
    {
        try {
            $roleId       = \Common\Permissions\Models\RoleUser::query()
                ->where('role_users.user_id', $user_id)->value('role_users.role_id');
            return \Common\Permissions\Models\RoleUser::query()->where('role_id', $roleId)->pluck('user_id')->toArray();
        }catch (\Exception $exception){
            return [];
        }
    }
}

if (!function_exists('secondsUntilEndOfToday')) {
    /**
     * 获取今天剩余的秒数（直到 23:59:59）
     *
     * @return int
     */
    function secondsUntilEndOfToday(): int
    {
        $now = \Carbon\Carbon::now();
        $endOfDay = $now->copy()->endOfDay();

        return $endOfDay->timestamp - $now->timestamp;
    }
}


/**
 * 计算计划名称长度（1 个汉字算 2 字符）
 */
if (!function_exists('getCustomLength')) {

    function getCustomLength(string $str): int
    {
        $len    = 0;
        $strLen = mb_strlen($str, 'UTF-8');

        for ($i = 0; $i < $strLen; $i++) {
            $char = mb_substr($str, $i, 1, 'UTF-8');
            // 如果是中文（匹配 CJK 统一汉字）
            if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $char)) {
                $len += 2;
            } else {
                $len += 1;
            }
        }

        return $len;
    }
}
if (!function_exists('generateOrderNo18')) {

    function generateOrderNo18(): int
    {
        // 10位时间戳（秒）
        $time = time(); // e.g. 1716979275

        // 4位微秒（防重复）
        $micro = (int)((float)microtime(true) * 1000000) % 10000; // 0~9999

        // 4位随机数
        $rand = mt_rand(1000, 9999); // 避免重复

        // 拼接成18位订单号
        return (int)($time . str_pad($micro, 4, '0', STR_PAD_LEFT) . $rand);
    }
}



/**
 * 判断是否为 yyyy-mm-dd 格式的日期
 */
if (!function_exists('isYmdDate')) {
    function isYmdDate(string $date): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt && $dt->format('Y-m-d') === $date;
    }
}



if (!function_exists('autoSplit')) {
    function autoSplit($str)
    {
        // 标准化：去掉两边空白
        $str = trim($str);

        // 如果包含换行符（\r\n 或 \n）
        if (preg_match("/\r\n|\n/", $str)) {
            return checkCtypeDigit(preg_split("/\r\n|\n/", $str, -1, PREG_SPLIT_NO_EMPTY));
        }

        // 如果包含中英文逗号
        if (preg_match("/,|，/", $str)) {
            return checkCtypeDigit(preg_split("/,|，/", $str, -1, PREG_SPLIT_NO_EMPTY));
        }

        // 默认按空白分隔（空格、tab 都可）
        return preg_split('/\s+/', $str, -1, PREG_SPLIT_NO_EMPTY);
    }


    function checkCtypeDigit($array)
    {
        $result = [];
        collect($array)->map(function ($id) use(&$result){
            if(!ctype_digit($id)){
                $id = autoSplit($id);
            }else{
                $id = array($id);
            }
            $result= array_merge($id,$result);
        });
        return $result;
    }
}


