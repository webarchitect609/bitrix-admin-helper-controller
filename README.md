Замена контроллера пакета `digitalwand/digitalwand.admin_helper`, которая позволяет не создавать свой модуль Битрикс 
для использования возможностей создания CRUD-страниц своих сущностей в админке.  

Будьте осторожны: пока нестабильная версия. 

Для использования библиотеки следует: 

1 Установить через composer

```
composer require webarchitect609/bitrix-admin-helper-controller
```

При этом в `bitrix/modules` будет установлен пакет digitalwand/digitalwand.admin_helper . Если требуется, чтобы он был 
установлен в `local/modules`, добавьте в `composer.json`:

```
"extra": {
    "installer-paths": {
      "local/modules/{$name}/": [
        "type:bitrix-module"
      ]
    }
  },
```

2 Следует создать скрипт подключения модуля на основе примера из `scripts/admin-helper-controller.php` и подключить его 
в файле `urlrewrite.php` при помощи правила: 

```
[
    'CONDITION' => '#^/bitrix/admin/admin_helper_controller.php#',
    'RULE'      => '',
    'ID'        => '',
    'PATH'      => '/admin-helper-controller.php',
],
```

В пути в `CONDITION` не используйте уровень ниже, чем `/bitrix/admin`, т.к. после добавления в меню и перехода по такому 
url все остальные ссылки станут неработоспособными.  


3 Первые два уровня в namespace генерируются на основании имени модуля, в котором vendor и package разделяются точкой. 
Значит обязательно требуется, чтобы все классы хелперов и визуального интерфейса были определены на третьем и более 
уровне, а у хелпера был бы указан `$module` из первых двух уровней namespace. При этом имя модуля следует указывать в 
том же регистре, в котором определён namespace. В противном случае класс не будет найден, т.к. несмотря на то, что 
namespace регистронезависимый, автолоадинг классов происходит в регистрозависимом режиме, т.к. в нём работает файловая 
система *nix подобной ОС. 

Например, 

```
<?php

namespace Foo\Bar\AdminInterface;

use Foo\Bar\Table\BarTaskTable;
use DigitalWand\AdminHelper\Helper\AdminEditHelper;

class BarTaskEditHelper extends AdminEditHelper
{
    protected static $model = BarTaskTable::class;

    public static $module = 'Foo.Bar';

    protected static $routerUrl = '/bitrix/admin/admin_helper_controller.php';

}
```


4 У хелпера должен быть переопределён $routerUrl в соответствии с правилом из urlrewrite.php  (см. в примере выше) 
Подсказка: т.к. статические члены класса не могут быть в обычном смысле переопределены, то рекомендуется использовать 
константы, чтобы избежать повторений, если используется несколько хелперов. 

5 Если в системе существует битрикс-модуль, указанный в $module у хелперов, и требуется его явно подключать, то 
используйте 

```
\WebArch\BitrixAdminHelperController\Controller::withTryIncludeModule(true)
```

При этом перед подключением $module строка будет приведена к нижнему регистру. По-умолчанию попытки подключить модуль 
не происходит. 


6 Имена классов хелперов должны быть составлены по маске <Entity_name>(List|Edit)Helper , а имя класса интерфейса по 
соответствующей маске <Entity_name>AdminInterface

7 Хелперы должны находиться строго на том же уровне, что и интерфейс.

Все описанные ограничения и неудобные правила, которые следует выполнять, вызваны тем, что пакет 
`digitalwand/digitalwand.admin_helper` написан в том же стиле ложного ООП, что и сам Битрикс, хотя решает очень насущную 
задачу.  

8 Добавить в мено, зарегистрировав обработчик события `main:OnBuildGlobalMenu` и получив URL от хелпера для списка.  

```
\Bitrix\Main\EventManager::getInstance()->addEventHandler(
        'main',
        'OnBuildGlobalMenu',
        function (&$adminMenu, &$moduleMenu) {
            $moduleMenu[] = [
                'parent_menu' => 'global_menu_store',
                'section'     => 'Bar task list',
                'sort'        => 610,
                'url'         => Foo\Bar\AdminInterface\BarTaskListHelper::getUrl(),
                'text'        => 'Bar task list',
                'title'       => 'Create and view bar tasks',
                'icon'        => 'sale_menu_icon_statistic',
                'page_icon'   => 'sale_page_icon_statistic',
                'item_id'     => 'my-bar-task-list',
                'items'       => [],
            ];
        }
);
```
 

