<?php

/* ========================================================================
 * Open eClass 3.0
 * E-learning and Course Management System
 * ========================================================================
 * Copyright 2003-2012  Greek Universities Network - GUnet
 * A full copyright notice can be read in "/info/copyright.txt".
 * For a full list of contributors, see "credits.txt".
 *
 * Open eClass is an open platform distributed in the hope that it will
 * be useful (without any warranty), under the terms of the GNU (General
 * Public License) as published by the Free Software Foundation.
 * The full license can be read in "/info/license/license_gpl.txt".
 *
 * Contact address: GUnet Asynchronous eLearning Group,
 *                  Network Operations Center, University of Athens,
 *                  Panepistimiopolis Ilissia, 15784, Athens, Greece
 *                  e-mail: info@openeclass.org
 * ======================================================================== */

$path = realpath(dirname(__FILE__));
require_once $path . '/../../config/config.php';
require_once $path . '/../../modules/admin/debug.php';
require_once $path . '/../db/database.php';
foreach (CloudDriveManager::$DRIVENAMES as $driveName)
    require_once 'plugins/' . strtolower($driveName) . '.php';

final class CloudDriveManager {

    public static $DRIVENAMES = array("GoogleDrive", "OneDrive", "Dropbox");

    const DRIVE = "clouddrive";
    const FILEPENDING = "pendingurl";

    private static $DRIVES = null;

    public static function getValidDrives() {
        if (CloudDriveManager::$DRIVES == null) {
            $drives = array();
            foreach (CloudDriveManager::$DRIVENAMES as $driveName) {
                require_once 'plugins/' . strtolower($driveName) . '.php';
                $drive = new $driveName();
                if ($drive->isPresent()) {
                    $drives[] = $drive;
                }
            }
            CloudDriveManager::$DRIVES = $drives;
        }
        return CloudDriveManager::$DRIVES;
    }

    public static function renderAsButtons() {
        $result = "\n<script src=\"../../js/colorbox/jquery.colorbox.min.js\"></script>
<link rel=\"stylesheet\" href=\"../../js/colorbox/colorbox.css\"/>
<script>
    function authorizeDrive(driveType) {
        win = window.open('../drives/popup.php?" . CloudDriveManager::DRIVE . "=' + driveType, 'Connecting... ' ,'height=600,width=400,scrollbars=yes');
        var timer = setInterval(function() {   
            if(win.closed) {  
                clearInterval(timer);
                window.location.reload();
            }
        }, 1000);
    }
    $(function ()
    {
        $(\".driveconn\").colorbox({iframe:true, innerWidth:424, innerHeight:330});    
    })
    function callback(file) {
        window.location.href = window.location.href + '&" . CloudDriveManager::FILEPENDING . "=' + encodeURIComponent(file);
    }
</script>\n";

        foreach (CloudDriveManager::getValidDrives() as $drive) {
            if ($drive->isAuthorized()) {
                $result .="<a class='btn btn-default driveconn' href=\"../drives/filebrowser.php?" . $drive->getDriveDefaultParameter() . "\"><i class='fa fa-file space-after-icon'/></i>" . $drive->getDisplayName() . "</a> \n";
            } else {
                $result .="<a class='btn btn-default' href=\"javascript:void(0)\" onclick=\"authorizeDrive('" . $drive->getName() . "')\"><i class='fa fa-plug space-after-icon'></i>" . $drive->getDisplayName() . "</a> \n";
            }
        }
        return "\n" . $result;
    }

    public static function getSessionDrive() {
        $drive_name = isset($_GET[CloudDriveManager::DRIVE]) ? $_GET[CloudDriveManager::DRIVE] : null;
        if ($drive_name == null) {
            die("Error while retrieving cloud connectivity");
        }
        foreach (CloudDriveManager::getValidDrives() as $drive) {
            if ($drive->getName() == $drive_name)
                return $drive;
        }
        return null;
    }

    public static function getFileUploadPending() {
        return isset($_GET[CloudDriveManager::FILEPENDING]) ? $_GET[CloudDriveManager::FILEPENDING] : null;
    }

}

abstract class CloudDrive {

    protected function getCallbackName() {
        return "code";
    }

    public function getCallbackToken() {
        $name = $this->getCallbackName();
        return isset($_GET[$name]) ? $_GET[$name] : null;
    }

    protected function getAuthorizeName() {
        return $this->getName() . "_session_authorize";
    }

    protected function setAuthorizeToken($code) {
        $_SESSION[$this->getAuthorizeName()] = $code;
    }

    public function getAuthorizeToken() {
        $name = $this->getAuthorizeName();
        return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
    }

    public function getName() {
        return strtolower(str_replace(' ', '', $this->getDisplayName()));
    }

    public function getDriveDefaultParameter() {
        return CloudDriveManager::DRIVE . "=" . $this->getName();
    }

    private function getConfig($key) {
        return Database::get()->querySingle("SELECT `value` FROM `config` WHERE `key` = ?s", "drive_" . $this->getName() . "_" . $key)->value;
    }

    protected function getClientID() {
        return $this->getConfig("clientid");
    }

    protected function getSecret() {
        return $this->getConfig("secret");
    }

    protected function getRedirect() {
        return $this->getConfig("redirect");
    }

    public abstract function getDisplayName();

    public abstract function isPresent();

    public abstract function isAuthorized();

    public abstract function getAuthURL();

    public abstract function authorize($callbackToken);

    public abstract function getFiles($dir);
}

final class CloudFile {

    private $name;
    private $downloadURL;
    private $isFolder;
    private $size;

    public function __construct($name, $downloadURL, $isFolder, $size) {
        $this->name = $name;
        $this->downloadURL = $downloadURL;
        $this->isFolder = $isFolder;
        $this->size = $size;
    }

    public function isFolder() {
        return $this->isFolder;
    }

    public function name() {
        return $this->name;
    }

    public function downloadURL() {
        return $this->downloadURL;
    }

    public function size() {
        return $this->size;
    }

}
