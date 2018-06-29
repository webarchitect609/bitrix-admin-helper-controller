<?php

namespace WebArch\BitrixAdminHelperController;

use Bitrix\Main\HttpRequest;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use DigitalWand\AdminHelper\Helper\AdminInterface;
use DigitalWand\AdminHelper\Helper\AdminListHelper;

/**
 * Class Controller
 * @package WebArch\BitrixAdminHelperController
 */
class Controller
{
    const QUERY_MODULE = 'module';

    const QUERY_VIEW = 'view';

    const QUERY_ENTITY = 'entity';

    const QUERY_POPUP = 'popup';

    const ADMIN_INTERFACE_CLASS_SUFFIX = 'AdminInterface';

    /**
     * @var bool
     */
    protected $tryLoadModule = false;

    /**
     * @param HttpRequest $request
     *
     * @throws LoaderException
     */
    public function show(HttpRequest $request)
    {
        $this->clearSessionSortParams();

        $isPopup = 'Y' == $request->getQuery(self::QUERY_POPUP);

        $module = trim($request->getQuery(self::QUERY_MODULE));
        $view = trim($request->getQuery(self::QUERY_VIEW));
        $entity = trim($request->getQuery(self::QUERY_ENTITY));

        if ('' == $view || !$this->tryLoadModule($module)) {
            $this->showAdmin404();
        }

        /** @var AdminInterface $adminInterfaceClassName */
        $adminInterfaceClassName = $this->getAdminInterfaceClassName($module, $view, $entity);
        if ('' != $adminInterfaceClassName && class_exists($adminInterfaceClassName)) {
            $adminInterfaceClassName::register();
        }

        list($helperClassName, $interfaceParams) = AdminBaseHelper::getGlobalInterfaceSettings($module, $view);
        if (!$helperClassName OR !$interfaceParams) {
            $this->showAdmin404();
        }

        $helper = static::createHelper($helperClassName, $interfaceParams, $isPopup);
        if (is_null($helper)) {
            $this->showAdmin404();
        }

        $this->doShow($helper, $isPopup);
    }

    /**
     * @internal Очищаем переменные сессии, чтобы сортировка восстанавливалась с учетом $table_id.
     */
    private function clearSessionSortParams()
    {
        global $APPLICATION;

        $uniqid = md5($APPLICATION->GetCurPage());

        $paramsToClean = ['SESS_SORT_BY', 'SESS_SORT_ORDER'];

        foreach ($paramsToClean as $param) {

            if (!isset($_SESSION[$param]) || !is_array($_SESSION[$param])) {
                continue;
            }

            if (array_key_exists($uniqid, $_SESSION[$param])) {
                unset($_SESSION[$param][$uniqid]);
            }
        }
    }

    private function showAdmin404()
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        global $APPLICATION, $USER;

        /** @noinspection PhpIncludeInspection */
        include $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/admin/404.php';
    }

    /**
     * @param string $module
     * @param string $view
     * @param string $entity
     *
     * @return string Имя класса типа \DigitalWand\AdminHelper\Helper\AdminInterface
     */
    private function getAdminInterfaceClassName(string $module, string $view, string $entity): string
    {
        // Собираем имя класса админского интерфейса
        $moduleNameParts = explode('.', $module);
        $entityNameParts = explode('_', $entity);
        $interfaceNameParts = array_merge($moduleNameParts, $entityNameParts);
        //Фильтруем пустые вхождения, чтобы не получить namespace с задвоенными backslash.
        $interfaceNameParts = array_filter(
            $interfaceNameParts,
            function ($string) {
                return trim($string) != '';
            }
        );
        $interfaceNameClass = '';
        $viewParts = explode('_', $view);

        $count = count($viewParts);
        for ($i = 0; $i < $count; $i++) {
            $interfaceName = implode('', array_map('ucfirst', $viewParts));
            $parts = $interfaceNameParts;
            $parts[] = $interfaceName . self::ADMIN_INTERFACE_CLASS_SUFFIX;
            $interfaceNameClass = implode('\\', $parts);
            if (class_exists($interfaceNameClass)) {
                break;
            } else {
                $className = array_pop($parts);
                $parts[] = self::ADMIN_INTERFACE_CLASS_SUFFIX;
                $parts[] = $className;
                $interfaceNameClass = implode('\\', $parts);
                if (class_exists($interfaceNameClass)) {
                    break;
                }
            }
            array_pop($viewParts);
        }

        return $interfaceNameClass;
    }

    /**
     * @param string $helperClassName
     * @param array $interfaceParams
     * @param bool $isPopup
     *
     * @return AdminBaseHelper
     */
    public static function createHelper(string $helperClassName, array $interfaceParams, bool $isPopup): AdminBaseHelper
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        global $by, $order, $APPLICATION, $USER, $adminPage, $adminMenu, $adminChain;

        require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

        $fields = [];
        if (isset($interfaceParams['FIELDS']) && is_array($interfaceParams['FIELDS'])) {
            $fields = $interfaceParams['FIELDS'];
        }

        $tabs = [];
        if (isset($interfaceParams['TABS']) && is_array($interfaceParams['TABS'])) {
            $tabs = $interfaceParams['TABS'];
        }

        if (is_subclass_of($helperClassName, AdminListHelper::class)) {

            /** @var AdminListHelper $helper */
            $helper = new $helperClassName($fields, $isPopup);

            $helper->buildList([$by => $order]);

            return $helper;

        } elseif (is_subclass_of($helperClassName, AdminBaseHelper::class)) {

            return new $helperClassName($fields, $tabs);
        }

        return null;
    }

    /**
     * @param string $module
     *
     * @return bool
     * @throws LoaderException
     */
    private function tryLoadModule(string $module): bool
    {
        if (!$this->isTryLoadModule()) {
            return true;
        }

        return Loader::includeModule(mb_strtolower($module));
    }

    /**
     * @return bool
     */
    public function isTryLoadModule(): bool
    {
        return $this->tryLoadModule;
    }

    /**
     * @param bool $tryIncludeModule
     *
     * @return $this
     */
    public function withTryIncludeModule(bool $tryIncludeModule)
    {
        $this->tryLoadModule = $tryIncludeModule;

        return $this;
    }

    /**
     * @param AdminBaseHelper $helper
     * @param bool $isPopup
     */
    protected function doShow(AdminBaseHelper $helper, bool $isPopup)
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        global $by, $order, $APPLICATION, $USER, $adminPage, $adminMenu, $adminChain;

        if ($isPopup) {
            require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_popup_admin.php');
        } else {
            require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
        }

        if ($helper instanceof AdminListHelper) {
            /** @var AdminListHelper $helper */
            $helper->createFilterForm();
        }

        $helper->show();

        if ($isPopup) {
            require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_popup_admin.php');
        } else {
            require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
        }
    }

}
