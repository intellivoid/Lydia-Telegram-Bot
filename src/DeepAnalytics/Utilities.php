<?php


    namespace DeepAnalytics;

    use DeepAnalytics\Objects\Date;
    use DeepAnalytics\Objects\HourlyData;
    use DeepAnalytics\Objects\MonthlyData;
    use MongoDB\Model\BSONDocument;

    /**
     * Class Utilities
     * @package DeepAnalytics
     */
    class Utilities
    {
        /**
         * Generates an hourly stamp
         *
         * @param int $year
         * @param int $month
         * @param $day
         * @return string
         */
        static function generateHourlyStamp(int $year, int $month, int $day): string
        {
            return "$year-$month-$day";
        }

        /**
         * Generates a month stamp
         *
         * @param int $year
         * @param int $month
         * @return string
         */
        static function generateMonthStamp(int $year, int $month): string
        {
            return "$year-$month";
        }

        /**
         * Generates an array of a 24 hour timeline
         *
         * @return array
         */
        static function generateHourArray(): array
        {
            $current_count = 0;
            $results = array();

            while(true)
            {
                if($current_count > 24)
                {
                    break;
                }

                $results[$current_count] = 0;
                $current_count += 1;
            }

            return $results;
        }

        /**
         * Generates an array of a monthly timeline
         *
         * @param int|null $month
         * @param int|null $year
         * @return array
         */
        static function generateMonthArray(int $month=null, int $year=null): array
        {
            if(is_null($month))
            {
                $month = (int)date('n');
            }

            if(is_null($year))
            {
                $year = (int)date('Y');
            }

            $last = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $results = Array();

            for ($day=1; $day<=$last; $day++)
            {
                $results[$day] = 0;
            }

            return $results;
        }

        /**
         * Constructs HourlyData object from BSONDocument
         *
         * @param BSONDocument|array|object $document
         * @return HourlyData
         */
        static function BSONDocumentToHourlyData($document): HourlyData
        {
            $DocumentData = (array)$document->jsonSerialize();
            $DocumentData['_id'] = (string)$DocumentData['_id'];
            $DocumentData['date'] = (array)$DocumentData['date']->jsonSerialize();
            $DocumentData['data'] = (array)$DocumentData['data']->jsonSerialize();

            return HourlyData::fromArray($DocumentData);
        }

        /**
         * Constructs MonthlyData object from BSONDocument
         *
         * @param BSONDocument|array|object $document
         * @return MonthlyData
         */
        static function BSONDocumentToMonthlyData($document): MonthlyData
        {
            $DocumentData = (array)$document->jsonSerialize();
            $DocumentData['_id'] = (string)$DocumentData['_id'];
            $DocumentData['date'] = (array)$DocumentData['date']->jsonSerialize();
            $DocumentData['data'] = (array)$DocumentData['data']->jsonSerialize();

            return MonthlyData::fromArray($DocumentData);
        }

        /**
         * Constructs the date object
         *
         * @param int|null $year
         * @param int|null $month
         * @param int|null $day
         * @return Date
         */
        static function constructDate(int $year=null, int $month=null, int $day=null): Date
        {
            $DateObject = new Date();

            if(is_null($year) == false)
            {
                $DateObject->Year = $year;
            }

            if(is_null($month) == false)
            {
                $DateObject->Month = $month;
            }

            if(is_null($day) == false)
            {
                $DateObject->Day = $day;
            }

            return $DateObject;
        }
    }