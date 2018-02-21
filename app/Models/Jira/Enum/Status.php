<?php namespace App\Models\Jira\Enum;

abstract class Status{

    const BACKLOG = 10100;
    const SELECTED_FOR_DEVELOPMENT = 10101;
    const TO_DO = 10002;
    const IN_PROGRESS = 3;
    const IN_TESTING = 10202;
    const FEEDBACK = 10400;
    const IN_REVIEW = 10001;
    const PRE_RELEASE = 10504;
    const DEPLOYED = 10201;
    const DONE = 10000;

    private static $constCacheArray = NULL;

    private static function getConstants() {
        if (self::$constCacheArray == NULL) {
            self::$constCacheArray = [];
        }
        $calledClass = get_called_class();
        if (!array_key_exists($calledClass, self::$constCacheArray)) {
            $reflect = new \ReflectionClass($calledClass);
            self::$constCacheArray[$calledClass] = $reflect->getConstants();
        }
        return self::$constCacheArray[$calledClass];
    }

    public static function getString($status){
        $constants = array_flip(self::getConstants());

        if(!array_key_exists($status, $constants)){
            throw new \Exception("Status value ({$status}) does not exist");
        }

        return ucwords(strtolower(str_replace('_', ' ', $constants[$status])));
    }

}