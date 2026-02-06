<?php
namespace sgpb;

class PopupData
{
        private static $popupData = array();

        private function __construct()
        {
        }

        public static function getPopupDataById($popupId, $saveMode = '')
        {
                if (!is_array(self::$popupData)) {
                        self::$popupData = [];
                }

                if (!is_scalar($popupId) || empty($popupId)) {
                        return null;
                }

                if (!array_key_exists($popupId, self::$popupData)) {
                        self::$popupData[$popupId] = SGPopup::getSavedData($popupId, $saveMode);
                }

                return self::$popupData[$popupId];
        }
}