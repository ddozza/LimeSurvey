<?php
/*
* LimeSurvey
* Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*
*	$Id$
*/

class UserIdentity extends CUserIdentity
{
    protected $id;
    protected $user;

    /**
    * Checks whether this user has correctly entered password or not
    *
    * @access public
    * @return bool
    */
    public function authenticate()
    {    
        if (Yii::app()->getConfig("auth_webserver")==false)
        {
            $user = User::model()->findByAttributes(array('users_name' => $this->username));

            if ($user !== null)
            {
                if (gettype($user->password)=='resource')
                {
                    $sStoredPassword=stream_get_contents($user->password);  // Postgres delivers bytea fields as streams :-o
                }
                else
                {
                    $sStoredPassword=$user->password;
                }
            }
            if ($user === null)
            {
                $this->errorCode = self::ERROR_USERNAME_INVALID;
            }
            else if ($sStoredPassword !== hash('sha256', $this->password))
            {
                $this->errorCode = self::ERROR_PASSWORD_INVALID;
            }
            else
            {
                $this->id = $user->uid;
                $this->user = $user;
                $this->errorCode = self::ERROR_NONE;
            }
        }
        elseif(Yii::app()->getConfig("auth_webserver") === true && isset($_SERVER['PHP_AUTH_USER']))    // normal login through webserver authentication
        {
            $sUser=$_SERVER['PHP_AUTH_USER'];
            $aUserMappings=Yii::app()->getConfig("auth_webserver_user_map");
            if (isset($aUserMappings[$sUser])) $sUser= $aUserMappings[$sUser];

            $oUser=User::model()->findByAttributes(array('users_name'=>$sUser));
            if (is_null($oUser))
            {
                if (function_exists("hook_get_auth_webserver_profile"))
                {
                    // If defined this function returns an array
                    // describing the defaukt profile for this user
                    $aUserProfile = hook_get_autouserprofile($sUser);
                }
                elseif (Yii::app()->getConfig("auth_webserver_autocreate_user"))
                {
                    $aUserProfile=Yii::app()->getConfig("auth_webserver_autocreate_profile");
                }
            }
            
            
            if (Yii::app()->getConfig("auth_webserver_autocreate_user") && isset($aUserProfile) && is_null($oUser))
            { // user doesn't exist but auto-create user is set
                $oUser=new User;
                $oUser->users_name=$sUser;
                $oUser->password=hash('sha256', createPassword());
                $oUser->full_name=$aUserProfile['full_name'];
                $oUser->parent_id=1;
                $oUser->lang=$aUserProfile['lang'];
                $oUser->email=$aUserProfile['email'];
                $oUser->create_survey=$aUserProfile['create_survey'];
                $oUser->create_user=$aUserProfile['create_user'];
                $oUser->delete_user=$aUserProfile['delete_user'];
                $oUser->superadmin=$aUserProfile['superadmin'];
                $oUser->configurator=$aUserProfile['configurator'];
                $oUser->manage_template=$aUserProfile['manage_template'];
                $oUser->manage_label=$aUserProfile['manage_label'];

                if ($oUser->save())
                {
                    $aTemplates=explode(",",$aUserProfile['templatelist']);
                    foreach ($arrayTemplates as $sTemplateName)
                    {
                        $oRecord=new Templates_rights;
                        $oRecord->uid = $oUser->uid;
                        $oRecord->folder = trim($sTemplateName);
                        $oRecord->use = 1;
                        $oRecord->save();
                    }

                    // read again user from newly created entry
                    $this->id = $oUser->uid;
                    $this->user = $oUser;                    
                    $this->errorCode = self::ERROR_NONE;                    
                }
                else
                {
                    $this->errorCode = self::ERROR_USERNAME_INVALID;
                }

            }
        }
        else
        {
            $this->errorCode = self::ERROR_USERNAME_INVALID;
        }
        return !$this->errorCode;
    }

    /**
    * Returns the current user's ID
    *
    * @access public
    * @return int
    */
    public function getId()
    {
        return $this->id;
    }

    /**
    * Returns the active user's record
    *
    * @access public
    * @return CActiveRecord
    */
    public function getUser()
    {
        return $this->user;
    }
}
