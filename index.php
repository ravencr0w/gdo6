<?php
use GDO\Core\Application;
use GDO\Core\Debug;
use GDO\Core\Logger;
use GDO\Language\Trans;
use GDO\UI\GDT_Page;
use GDO\User\GDO_User;
use GDO\Session\GDO_Session;
use GDO\DB\Database;
use GDO\Core\ModuleLoader;
use GDO\Core\Website;
use GDO\Core\GDT_Error;
use GDO\Core\Method\Page404;
use GDO\Util\Strings;
use GDO\DB\Cache;
use GDO\Core\GDT_Response;
use GDO\Core\GDT_Hook;
use GDO\UI\GDT_HTML;

require 'GDO6.php';

@include GDO_PATH . 'protected/config.php';
if (!defined('GDO_CONFIGURED'))
{
    require 'index_install.php';
    die(0);
}

GDT_Page::make();
$response = GDT_Response::make();

Database::init();
new ModuleLoader(GDO_PATH . 'GDO/');
$noSession = true;
if (@class_exists('\\GDO\\Session\\GDO_Session', true))
{
    $noSession = false;
    GDO_Session::init(GDO_SESS_NAME, GDO_SESS_DOMAIN, GDO_SESS_TIME, !GDO_SESS_JS, GDO_SESS_HTTPS);
}

$app = new Application();

# Bootstrap
Trans::$ISO = GDO_LANGUAGE;
Logger::init(null, GDO_ERROR_LEVEL); # 1st init as guest
Debug::init();
Debug::enableErrorHandler();
Debug::enableExceptionHandler();
Debug::setDieOnError(GDO_ERROR_DIE);
Debug::setMailOnError(GDO_ERROR_MAIL);
ModuleLoader::instance()->loadModulesCache();
if (!module_enabled('Core'))
{
    require 'index_install.php';
    die(1);
}

if (!$noSession)
{
    $session = GDO_Session::instance();
}
if (GDO_User::current()->isUser())
{
    if ($name = GDO_User::current()->getUserName())
    {
        $name = str_replace(GDO_User::GUEST_NAME_PREFIX, '_', $name);
    	Logger::init($name, GDO_ERROR_LEVEL); # 2nd init with username
    }
}

if (GDO_LOG_REQUEST)
{
    Logger::logRequest();
}

# All fine!
define('GDO_CORE_STABLE', 1);
try
{
	$rqmethod = $_SERVER['REQUEST_METHOD'];
	if (!in_array($rqmethod, ['GET', 'POST', 'HEAD', 'OPTIONS'], true))
	{
		die('HTTP method not processed: ' . html($rqmethod));
	}

	# Exec
    ob_start();
    if (!isset($_REQUEST['mo']))
    { 
        # If we are not index or index, and not start with a query string immediately we have a 404 error.
        $f = $_SERVER['REQUEST_URI'];
        if ( (($f !== (GDO_WEB_ROOT.'index.php')) && ($f !== GDO_WEB_ROOT))
            && (!Strings::startsWith($f, GDO_WEB_ROOT.'?')) )
        {
            $method = Page404::make();
            $_GET['mo'] = $_REQUEST['mo'] = 'Core';
            $_GET['me'] = $_REQUEST['me'] = 'Page404';
        }
    }
    
    if (!isset($method))
    {
        $method = $app->getMethod();
    }

    if (GDO_DB_ENABLED && GDO_SESS_LOCK && $method->isLockingSession() && $session)
    {
        $lock = 'sess_'.$session->getID();
        Database::instance()->lock($lock);
        if (!$noSession)
        {
            GDO_Session::instance()->setLock($lock);
        }
    }
    
    GDT_Hook::callHook('BeforeRequest', $method);
    
    $cacheContent = '';
    if ($method->fileCached())
    {
        $cacheContent = $cacheLoad = $method->fileCacheContent();
    }
   
    if ($cacheContent)
    {
        $response = null;
        $content = $cacheContent;
        $method->setupSEO();
    }
    else
    {
        $response = $method->exec();
    }

    GDT_Hook::callHook('AfterRequest', $method);
}
catch (Throwable $e)
{
	Logger::logException($e);
	Debug::debugException($e, false); # send exception mail
	$response = GDT_Error::responseException($e);
}
finally
{
    $strayContent = ob_get_contents();
    ob_end_clean();
}


# Render Page
switch ($app->getFormat())
{
    case 'cli':
        if ($response)
        {
            hdr('Content-Type: application/text');
            $content = $strayContent;
            $cacheContent = $response->renderCLI();
            $content .= $cacheContent;
        }
        if ($session) $session->commit();
        break;
    case 'json':
        hdr('Content-Type: application/json');
        if ($response)
        {
            if ($strayContent)
            {
                $response->addField(GDT_HTML::make('content')->html($strayContent));
            }
            $response->addField(Website::$TOP_RESPONSE);
            $content = $response->renderJSON();
            if ($session)
            {
                $session->commit();
            }
            $content = $cacheContent = Website::renderJSON($content);
        }
        elseif ($session)
        {
            $session->commit();
        }
        break;
        
    case 'html':
        
        $ajax = Application::instance()->isAjax();
        
        if ($response)
        {
            $content = $strayContent;
            $cacheContent = $response->renderHTML();
            $content .= $cacheContent;
            if (!$ajax)
            {
                $content = GDT_Page::$INSTANCE->html($content)->render();
            }
            if ($session) $session->commit();
        }
        else
        {
            if (!$ajax)
            {
                $content = GDT_Page::$INSTANCE->html($cacheContent)->render();
            }
            if ($session) $session->commit();
        }
        break;
        
    case 'xml':
        hdr('Content-Type: application/xml');
        if ($response)
        {
            $content = $cacheContent = $response->renderXML();
        }
        if ($session) $session->commit();
        break;
}

if (isset($method) && $method->fileCached() && (!$cacheLoad))
{
    $key = $method->fileCacheKey();
    Cache::fileSet($key, $cacheContent);
}

# Fire recache IPC events.
Cache::recacheHooks();

echo $content;
