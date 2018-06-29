<?php

use Bitrix\Main\Application;
use WebArch\BitrixAdminHelperController\Controller;

/** @noinspection PhpUnhandledExceptionInspection */
(new Controller())->show(Application::getInstance()->getContext()->getRequest());
