<?php

namespace Common\Permissions\Repositories;

use Illuminate\Container\Container as Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Prettus\Repository\Eloquent\BaseRepository;
use Common\Permissions\Models\Menu;
use Common\Permissions\Models\Authority;
use Common\Exceptions\RepositoryException;
use Common\Libs\Common;

/**
 * Class UserRepositoryEloquent.
 *
 * @package namespace App\Repositories;
 */
class MenuRepositoryEloquent extends BaseRepository
{


    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Menu::class;
    }

    public function createMenu($data)
    {
        // 兼容子系统创建菜单，采用子系统的system_number
        $system_num = $data['system_num'] ?? 'RD0001';
        $row = $this->where('label', $data['label'])->where('index', $data['index'])->first();
        if ($row) {
            throw new RepositoryException('菜单不能重复或已经存在', 20000);
        }

        return $this->insert(
            [
                'pid'           => $data['pid'],
                'label'         => $data['label'],
                'ico'           => $data['ico'],
                'index'         => $data['index'],
                'state'         => $data['state'] ?? '',
                'type'          => $data['type'] ?? Menu::TYPE_MENU,
                'sort'          => $data['sort'] ?? 0,
                'subsystem_num' => $system_num
            ]
        );
    }

    /**
     * 菜单列表
     * @return array
     */
    public function listMenu($data)
    {
        $common = app(Common::class);

        $menuItems = $this->getModel()
            ->select(['id', 'pid', 'label', 'ico', 'index', 'state', 'type', 'sort', 'subsystem_num',
                      DB::raw("CASE WHEN type = " . Menu::TYPE_AUTHORIZE . " THEN server_index ELSE `index` END AS `index`")
            ])
            ->whereIn('type', [Menu::TYPE_MENU, Menu::TYPE_AUTHORIZE])
            ->orderBy('sort', 'DESC')
            ->orderBy('id', 'ASC')
            ->get()
            ->toArray();

        $grouped = collect($menuItems)->groupBy('subsystem_num');

        $menuData = [];
        foreach ($grouped as $subsystem => $items) {
            $menuData[$subsystem] = $common->getTree($items);
        }

        return $menuData;
    }

    /**
     * 更新菜单
     */
    public function menuProcessing($data)
    {
        //判断数据是否存在
        if (!$this->isExist($data['id']))
            throw new RepositoryException('数据不存在', 21123);
        //更新
        if ($this->where(['id' => $data['id']])->update($data))
            return true;
        else
            return false;
    }

    /**
     * 删除
     */
    public function deleteMenu($data)
    {
        $menuId = $data['id'];
        //判断数据是否存在
        if (!$this->isExist($data['id']))
            throw new RepositoryException('数据不存在', 21123);

        // 删除自身和所有子菜单
        $idsToDelete = $this->getAllDescendantIds($menuId);
        $idsToDelete[] = $menuId;

        return $this->whereIn('id', $idsToDelete)->delete() > 0;
    }

    protected function getAllDescendantIds($parentId)
    {
        $children = $this->where('pid', $parentId)->get(['id']);
        $ids = [];

        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getAllDescendantIds($child->id)); // 递归查找
        }

        return $ids;
    }


    /**
     * 查看是否存在
     */
    public function isExist($id)
    {
        return $this->where(['id' => $id])->count();
    }

}
