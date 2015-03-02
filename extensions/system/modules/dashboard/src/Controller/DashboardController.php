<?php

namespace Pagekit\Dashboard\Controller;

use Pagekit\Application as App;
use Pagekit\Application\Controller;
use Pagekit\Application\Exception;
use Pagekit\User\Entity\User;
use Pagekit\User\Model\UserInterface;
use Pagekit\Widget\Model\Widget;

/**
 * @Access(admin=true)
 */
class DashboardController extends Controller
{
    /**
     * @var DashboardModule
     */
    protected $dashboard;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->dashboard = App::module('system/dashboard');
    }

    /**
     * @Response("extensions/system/modules/dashboard/views/admin/index.php")
     */
    public function indexAction()
    {
        $widgets = [];
        $columns = [];

        foreach ($this->dashboard->getWidgets() as $id => $data) {
            if ($type = $this->dashboard->getType($data['type'])) {
                $widgets[$id] = $type->render($this->create($id, $data));
                $columns[] = $id;
            }
        }

        $columns = $this->chunkList($columns, 3);

        return ['head.title' => __('Dashboard'), 'theme.boxed' => false, 'widgets' => $widgets, 'columns' => $columns];
    }

    /**
     * @Response("extensions/system/modules/dashboard/views/admin/settings.php")
     */
    public function settingsAction()
    {
        App::scripts('dashboard', [
            'types' => $this->dashboard->getTypes(),
            'widgets' => $this->dashboard->getWidgets()
        ], [], 'export');

        return ['head.title' => __('Dashboard Settings')];
    }

    /**
     * @Request({"type"})
     * @Response("extensions/system/modules/dashboard/views/admin/edit.php")
     */
    public function addAction($id)
    {
        try {

            if (!$type = $this->dashboard->getType($id)) {
                throw new Exception(__('Invalid widget type.'));
            }

            $widget = new Widget;
            $widget->setType($id);

            return ['head.title' => __('Add Widget'), 'type' => $type, 'widget' => $widget];

        } catch (Exception $e) {
            App::message()->error($e->getMessage());
        }

        return $this->redirect('@system/dashboard/settings');
    }

    /**
     * @Request({"id"})
     * @Response("extensions/system/modules/dashboard/views/admin/edit.php")
     */
    public function editAction($id)
    {
        try {

            if (!$widget = $this->dashboard->getWidget($id)) {
                throw new Exception(__('Invalid widget id.'));
            }

            if (!$type = $this->dashboard->getType($widget['type'])) {
                throw new Exception(__('Invalid widget type.'));
            }

            $widget = $this->create($id, $widget);

            return ['head.title' => __('Edit Widget'), 'type' => $type, 'widget' => $widget];

        } catch (Exception $e) {
            App::message()->error($e->getMessage());
        }

        return $this->redirect('@system/dashboard/settings');
    }

    /**
     * @Request({"id", "widget": "array"}, csrf=true)
     */
    public function saveAction($id = 0, $widget = [])
    {
        try {

            if ($new = !$id) {
                $id = uniqid();
            }

            $this->save(array_merge($this->dashboard->getWidgets(), [$id => $widget]));

            App::message()->success($new ? __('Widget created.') : __('Widget saved.'));

        } catch (Exception $e) {
            App::message()->error($e->getMessage());
        }

        return $this->redirect($id ? '@system/dashboard/edit' : '@system/dashboard/add', compact('id'));
    }

    /**
     * @Request({"ids": "array"}, csrf=true)
     * @Response("json")
     */
    public function deleteAction($ids = [])
    {
        $widgets = $this->dashboard->getWidgets();

        foreach ($ids as $id) {
            unset($widgets[$id]);
        }

        $this->save($widgets);

        return ['message' => _c('{0} No widgets deleted.|{1} Widget deleted.|]1,Inf[ Widgets deleted.', count($ids)), 'widgets' => $widgets];
    }

    /**
     * @Request({"order": "array"}, csrf=true)
     * @Response("json")
     */
    public function reorderAction($order = [])
    {
        // $reordered = [];
        // $widgets = $this->dashboard->getWidgets();

        // foreach ($order as $data) {
        //     $id = $data['id'];
        //     if (isset($widgets[$id])) {
        //         $reordered[$id] = $widgets[$id];
        //     }
        // }

        // $this->save($reordered);

        return ['message' => __('Widgets reordered.')];
    }

    /**
     * @param string $id
     * @param array  $data
     * @return Widget
     */
    protected function create($id, $data)
    {
        $widget = new Widget;
        $widget->setId($id);
        $widget->setType($data['type']);
        $widget->setSettings($data);

        return $widget;
    }

    /**
     * @param string[]      $dashboard
     * @param UserInterface $user
     */
    protected function save($dashboard, $user = null)
    {
        if (null === $user) {
            $user = App::user();
        }

        // make sure user is registered in the entity manager
        $user = User::find($user->getId());
        $user->set('dashboard', $dashboard);
        $user->save();
    }

    protected function chunkList($list, $p) {

        $listlen   = count($list);
        $partlen   = floor($listlen / $p);
        $partrem   = $listlen % $p;
        $partition = [];
        $mark      = 0;

        for ($px = 0; $px < $p; $px++) {
            $incr = ($px < $partrem) ? $partlen + 1 : $partlen;
            $partition[$px] = array_slice($list, $mark, $incr);
            $mark += $incr;
        }

        return $partition;
    }
}
