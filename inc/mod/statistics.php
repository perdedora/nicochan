<?php

class Statistic
{
    private static function build24hQuery($boardName, $boards, $realtime)
    {
        if ($boardName) {
            return sprintf(
                "SELECT COUNT(1) AS count, HOUR(FROM_UNIXTIME(time)) AS hour FROM posts_%s WHERE DATE(FROM_UNIXTIME(time)) = CURDATE() %s GROUP BY hour",
                $boardName,
                $realtime ? "" : "AND HOUR(FROM_UNIXTIME(time)) <= HOUR(NOW() - INTERVAL 1 HOUR)"
            );
        }

        if ($boards === false) {
            $boards = listBoards();
        }

        $subQueries = array_map(function ($board) use ($realtime) {
            return sprintf(
                "SELECT COUNT(1) AS count, HOUR(FROM_UNIXTIME(time)) AS hour FROM posts_%s WHERE DATE(FROM_UNIXTIME(time)) = CURDATE() %s GROUP BY hour",
                $board['uri'],
                $realtime ? "" : "AND HOUR(FROM_UNIXTIME(time)) <= HOUR(NOW() - INTERVAL 1 HOUR)"
            );
        }, $boards);

        return "SELECT SUM(count) AS count, hour FROM (" . implode(" UNION ALL ", $subQueries) . ") AS deriv_all GROUP BY hour ORDER BY hour ASC";
    }

    public static function get_stat_24h($boardName = false, $realtime = true, $boards = false)
    {
        $query = self::build24hQuery($boardName, $boards, $realtime);
        $query = query($query) or error(db_error($query));
        $query_result = $query->fetchAll(PDO::FETCH_ASSOC);

        if (empty($query_result)) {
            $query_result = [['hour' => 0, 'count' => '0']];
        }

        $statistics_hour = array_fill(0, 24, 0);
        foreach ($query_result as $hour_data) {
            $statistics_hour[$hour_data['hour']] = $hour_data['count'];
        }

        $last_hour = end($query_result)['hour'];
        if ($last_hour != 23) {
            for ($i = $last_hour + 1; $i < 24; $i++) {
                $statistics_hour[$i] = 'null';
            }
        }

        return json_encode($statistics_hour);
    }

    private static function buildWeekQuery($boardName, $boards, $previous_week, $realtime, $hour_realtime)
    {
        $timeCondition = '';

        if ($previous_week) {
            if ($realtime) {
                $timeCondition = "YEARWEEK(FROM_UNIXTIME(time), 1) = YEARWEEK(DATE_SUB(NOW(), INTERVAL 1 WEEK), 1)";
            } elseif ($hour_realtime) {
                $timeCondition = "YEARWEEK(FROM_UNIXTIME(time), 1) = YEARWEEK(DATE_SUB(NOW() - INTERVAL 1 HOUR, INTERVAL 1 WEEK), 1)";
            } else {
                $timeCondition = "YEARWEEK(FROM_UNIXTIME(time), 1) = YEARWEEK(DATE_SUB(NOW() - INTERVAL 1 DAY, INTERVAL 1 WEEK), 1)";
            }
        } else {
            if ($realtime) {
                $timeCondition = "YEARWEEK(FROM_UNIXTIME(time), 1) = YEARWEEK(NOW(), 1)";
            } elseif ($hour_realtime) {
                $timeCondition = "YEARWEEK(FROM_UNIXTIME(time), 1) = YEARWEEK(NOW() - INTERVAL 1 HOUR, 1)";
            } else {
                $timeCondition = "YEARWEEK(FROM_UNIXTIME(time), 1) = YEARWEEK(NOW() - INTERVAL 1 DAY, 1)";
            }
        }

        if ($boardName) {
            return sprintf(
                "SELECT COUNT(1) AS count, WEEKDAY(FROM_UNIXTIME(time)) AS day FROM posts_%s WHERE %s GROUP BY day",
                $boardName,
                $timeCondition
            );
        }

        if ($boards === false) {
            $boards = listBoards();
        }

        $subQueries = array_map(function ($board) use ($timeCondition) {
            return sprintf(
                "SELECT COUNT(1) AS count, WEEKDAY(FROM_UNIXTIME(time)) AS day FROM posts_%s WHERE %s GROUP BY day",
                $board['uri'],
                $timeCondition
            );
        }, $boards);

        return "SELECT SUM(count) AS count, day FROM (" . implode(" UNION ALL ", $subQueries) . ") AS deriv_all GROUP BY day ORDER BY day ASC";
    }

    public static function get_stat_week($previous_week = false, $boardName = false, $realtime = true, $hour_realtime = true, $boards = false)
    {
        $query = self::buildWeekQuery($boardName, $boards, $previous_week, $realtime, $hour_realtime);
        $query = query($query) or error(db_error($query));
        $query_result = $query->fetchAll(PDO::FETCH_ASSOC);

        $statistics_week = array_fill(0, 7, 0);
        foreach ($query_result as $day_data) {
            $statistics_week[$day_data['day']] = $day_data['count'];
        }

        return json_encode($statistics_week);
    }

    public static function get_stat_week_labels($week_data)
    {
        $week_data = json_decode($week_data, true);

        if (is_array($week_data) && count($week_data) === 7) {
            $labels = [
                "Monday\n({$week_data[0]})",
                "Tuesday\n({$week_data[1]})",
                "Wednesday\n({$week_data[2]})",
                "Thursday\n({$week_data[3]})",
                "Friday\n({$week_data[4]})",
                "Saturday\n({$week_data[5]})",
                "Sunday\n({$week_data[6]})"
            ];
            return json_encode($labels);
        }

        return json_encode([]);
    }


    public static function getPostStatistics($boards)
    {
        global $config;

        if (!isset($config['boards'])) {
            return null;
        }

        $HOUR = 3600;
        $DAY = $HOUR * 24;
        $WEEK = $DAY * 7;

        $stats = [];
        $hourly = [];
        $daily = [];
        $weekly = [];

        foreach ($boards as $board) {
            if (!array_key_exists('uri', $board)) {
                continue;
            }
            $_board = getBoardInfo($board['uri']);
            if (!$_board) {
                continue;
            }

            $boardStat['title'] = $_board['uri'];
            $boardStat['hourly_ips'] = self::countUniqueIps($hourly, $HOUR, $_board);
            $boardStat['daily_ips'] = self::countUniqueIps($daily, $DAY, $_board);
            $boardStat['weekly_ips'] = self::countUniqueIps($weekly, $WEEK, $_board);

            $pph_query = query(
                sprintf(
                    "SELECT COUNT(1) AS count FROM ``posts_%s`` WHERE time > %d",
                    $_board['uri'],
                    time() - 3600
                )
            ) or error(db_error());

            $boardStat['pph'] = $pph_query->fetch()['count'];
            $stats['boards'][] = $boardStat;
        }

        $stats['hourly_ips'] = count($hourly);
        $stats['daily_ips'] = count($daily);
        $stats['weekly_ips'] = count($weekly);
        $stats['pph'] = array_sum(array_column($stats['boards'], 'pph'));

        return $stats;
    }

    private static function countUniqueIps(&$markAsCounted, $timespan, $_board)
    {
        $unique_query = query(
            sprintf(
                "SELECT DISTINCT ip FROM ``posts_%s`` WHERE time > %d",
                $_board['uri'],
                time() - $timespan
            )
        ) or error(db_error());

        $uniqueIps = $unique_query->fetchAll();
        foreach ($uniqueIps as $row) {
            $markAsCounted[$row['ip']] = true;
        }

        return count($uniqueIps);
    }
}
