<?php

declare(strict_types=1);

namespace App\Services;

final class RiskService
{
    public function evaluate(array $m, array $settings): array
    {
        $score = 0;
        $reasons = [];

        $fpg = (float) ($m['fasting_glucose'] ?? 0);
        $ogtt = (float) ($m['ogtt_2h'] ?? 0);
        $hba1c = (float) ($m['hba1c'] ?? 0);
        $sbp = (int) ($m['sbp'] ?? 0);
        $dbp = (int) ($m['dbp'] ?? 0);
        $bmi = (float) ($m['bmi'] ?? 0);
        $tg = (float) ($m['triglyceride'] ?? 0);
        $ldl = (float) ($m['ldl'] ?? 0);

        if ($fpg >= 7.0) {
            $score += 30;
            $reasons[] = '空腹血糖升高';
        } elseif ($fpg >= 6.1) {
            $score += 20;
            $reasons[] = '空腹血糖受损';
        }

        if ($ogtt >= 11.1) {
            $score += 20;
            $reasons[] = '糖耐量异常明显';
        } elseif ($ogtt >= 7.8) {
            $score += 15;
            $reasons[] = '糖耐量异常';
        }

        if ($hba1c >= 6.5) {
            $score += 15;
            $reasons[] = '糖化血红蛋白偏高';
        } elseif ($hba1c >= 5.7) {
            $score += 10;
            $reasons[] = '糖化血红蛋白临界';
        }

        if ($sbp >= 140 || $dbp >= 90) {
            $score += 10;
            $reasons[] = '血压异常';
        }

        if ($bmi >= 28) {
            $score += 10;
            $reasons[] = '肥胖';
        } elseif ($bmi >= 24) {
            $score += 6;
            $reasons[] = '超重';
        }

        if ($tg >= 2.3) {
            $score += 6;
            $reasons[] = '甘油三酯偏高';
        }

        if ($ldl >= 3.4) {
            $score += 6;
            $reasons[] = '低密度脂蛋白偏高';
        }

        foreach (['family_history', 'smoker', 'drinker', 'inactivity'] as $factor) {
            if (!empty($m[$factor])) {
                $score += 4;
            }
        }

        $lineYellow = (int) ($settings['line_yellow'] ?? 40);
        $lineRed = (int) ($settings['line_red'] ?? 70);

        $line = 'green';
        if ($score >= $lineRed) {
            $line = 'red';
        } elseif ($score >= $lineYellow) {
            $line = 'yellow';
        }

        $level = 'Ⅰ';
        if ($score >= 80) {
            $level = 'Ⅳ';
        } elseif ($score >= 60) {
            $level = 'Ⅲ';
        } elseif ($score >= 40) {
            $level = 'Ⅱ';
        }

        $prediabetes = ($fpg >= 6.1 && $fpg < 7.0) || ($ogtt >= 7.8 && $ogtt < 11.1) ? 1 : 0;

        return [
            'score' => $score,
            'line_color' => $line,
            'management_level' => $level,
            'prediabetes' => $prediabetes,
            'reason' => implode('；', array_unique($reasons)),
        ];
    }

    public function interventionPlan(string $line, string $level): array
    {
        $common = [
            '饮食' => '控制总热量，减少精制糖摄入，增加蔬菜和优质蛋白。',
            '运动' => '每周至少150分钟中等强度运动，避免久坐。',
            '生活方式' => '规律作息，戒烟限酒，管理压力。',
            '监测' => '按计划进行血糖、血压、体重监测。',
            '复查' => '按医嘱进行糖化血红蛋白与血脂复查。',
        ];

        if ($line === 'red') {
            $common['强化管理'] = '纳入重点人群，2周内完成一次随访，必要时转诊专科。';
        } elseif ($line === 'yellow') {
            $common['重点提醒'] = '纳入黄线预警名单，1个月内复测关键指标。';
        }

        $common['管理等级'] = '当前管理等级：' . $level;

        return $common;
    }
}
