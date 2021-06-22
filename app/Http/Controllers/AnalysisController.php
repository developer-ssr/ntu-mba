<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\User;
use App\Traits\Functions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnalysisController extends Controller
{
    private $persona_id = 0;

    public function getIndividualData(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $phase4 = $this->phase4($user->scores['link1']['phase4'], $user);
            $phase2 = $this->phase2($this->replaceZ($user->scores['link1']['phase2']));
            $phaseB = $this->phaseB($user->scores['link1']['phaseB']);
            $insight2 = $this->insight2v3($user->scores['link1']['phase2'], $user);
            $emotional_insights = $this->insight3v2($user->scores['link1']['phaseB']);
            
            return [
                'main' => [
                    'insight' => $phase4,
                    'insight2' => $insight2,
                    'name' => 'Main Survey',
                    'url' => env('APP_URL') . 'route?link=1',
                    'titles' => [
                        'label' => 'Main Survey',
                        'ch1' => 'ASPECTS OF YOUR SURVEY',
                        'ch2' => 'YOUR FULFILMENT EMOTIONS PILLAR: <strong>MAIN SURVEY</strong>',
                    ],
                    'data' => $phase2,
                    'start' => $user->config['link1']['start'] ?? null,
                    'end' => $user->config['link1']['end'] ?? null,
                    'id' => 1,
                    'data2' => $phaseB['data'],
                    'insight3' => $emotional_insights,
                    'persona_id' => $this->persona_id,
                ],
                
            ];
        }
    }

    /* Revision November 2, 2020 specs
     *
     */
    public function insight2v2($score, $user): string
    {
        $relative = $score['relative'];
        $current = $score['current'];
        $me = [
            'WORK' => 'My work',
            'MONEY' => '',
            'SOCIETY' => '',
            'SELF' => '',
            'RELATIONSHIPS' => ''
        ];
        $you = [
            'WORK' => '<strong>Your Work</strong>',
            'MONEY' => '<strong>Your Money</strong>',
            'SOCIETY' => '<strong>Society</strong>',
            'SELF' => '<strong>Your Sense of Self</strong>',
            'RELATIONSHIPS' => '<strong>Your Relationships</strong>'
        ];
        $ranking_text = [
            'work' => [
                'a' => 'You rank your current satisfaction with <strong>Your Work</strong> higher than the importance you assign to it. Take another of our surveys to find more about why this might be.',
                'b' => 'You rank your current satisfaction with <strong>Your Work</strong> lower than the importance you assign to it. You might like to deep dive into this area of your life by taking the <strong>My Work survey.',
                'c' => 'You rank your current satisfaction with <strong>Your Work</strong> at about the same level as the importance you assign to it.'
            ],
            'self' => [
                'a' => 'You rank your current satisfaction with <strong>Your Sense of Self</strong> higher than the importance you assign to it. Find out more with our deep dive surveys' . (is_null($user->email) ? " - register first using the form below." : "."),
                'b' => 'You rank your current satisfaction with <strong>Your Sense of Self</strong> lower than the importance you assign to it. ' . (is_null($user->email) ? "Register below and y" : "Y") . "ou can deep dive into this area of your life by taking the My Self survey.",
                'c' => 'You rank your current satisfaction with <strong>Your Sense of Self</strong> at about the same level as the importance you assign to it. ' . (is_null($user->email) ? "Register below to t" : "T") . "ake more surveys on these important lifestyle areas.",
            ],
            'society' => [
                'a' => 'You rank your current satisfaction with <strong>Society</strong> higher than the importance you assign to it.',
                'b' => 'You rank your current satisfaction with <strong>Society</strong> lower than the importance you assign to it.',
                'c' => 'You rank your current satisfaction with <strong>Society</strong> at about the same level as the importance you assign to it.'
            ],
            'money' => [
                'a' => 'You rank your current satisfaction with <strong>Your Money</strong> higher than the importance you assign to it. ',
                'b' => 'You rank your current satisfaction with <strong>Your Money</strong> lower than the importance you assign to it. You might like to deep dive into this area of your life by taking the My Money survey.',
                'c' => 'You rank your current satisfaction with <strong>Your Money</strong> at about the same level as the importance you assign to it.'
            ],
            'relationships' => [
                'a' => 'You rank your current satisfaction with <strong>Your Relationships</strong> higher than the importance you assign to it.',
                'b' => 'You rank your current satisfaction with <strong>Your Relationships</strong> lower than the importance you assign to it.',
                'c' => 'You rank your current satisfaction with <strong>Your Relationships</strong> at about the same level as the importance you assign to it.'
            ]
        ];
        $relative_ranked = $this->rank($relative);
        $current_ranked = $this->rank($current);
        $diffs = collect($relative_ranked)->map(function ($value, $key) use ($current_ranked) {
            return $value - $current_ranked[$key];
        });
        $absolute_diffs = collect($diffs)->map(function ($x) {
            return abs($x);
        });
        //return $relative_ranked;
        $rank1_relative = collect($relative)->sortDesc()->first();
        $rank1_relative_count = collect($relative)->filter(function ($res) use ($rank1_relative) {
            return $res == $rank1_relative;
        })->count();
        $str = '';
        switch ($rank1_relative_count) {
            case 1:
                $str = 'the most important area is ' . $you[collect($relative)->sortDesc()->keys()->first()] . " and the least important area is " . $you[collect($relative)->sort()->keys()->first()];
                break;
            case 2:
                $str = 'the two most important areas are ' . $you[collect($relative)->sortDesc()->keys()->first()] . " and " . $you[collect($relative)->sortDesc()->keys()[1]] . ", and the least important area is " . $you[collect($relative)->sort()->keys()->first()];
                break;
            case 3:
                $str = 'the three most important areas are ' . $you[collect($relative)->sortDesc()->keys()->first()] . ", " . $you[collect($relative)->sortDesc()->keys()[1]] . " and " . $you[collect($relative)->sortDesc()->keys()[2]] . ", and the least important area is " . $you[collect($relative)->sort()->keys()->first()];
                break;
            case 4:
                $str = 'the four most important areas are ' . $you[collect($relative)->sortDesc()->keys()->first()] . ", " . $you[collect($relative)->sortDesc()->keys()[1]] . ", " . $you[collect($relative)->sortDesc()->keys()[2]] . " and " . $you[collect($relative)->sortDesc()->keys()[3]] . ", and the least important area is " . $you[collect($relative)->sort()->keys()->first()];
                break;
            case 5:
                $str = "Virtually <strong>ALL</strong> areas of " . join(' and ', array_filter(array_merge(array(join(', ', array_slice($you, 0, -1))), array_slice($you, -1)), 'strlen')) . " are equally important.";
                break;
        }

        $rank1_current = collect($current)->sortDesc()->first();
        $rank1_current_count = collect($current)->filter(function ($res) use ($rank1_current) {
            return $res == $rank1_current;
        })->count();
        $str2 = '';
        switch ($rank1_current_count) {
            case 1:
                $str2 = "You are most satisfied with " . $you[collect($current)->sortDesc()->keys()->first()] . " and least satisfied with " . $you[collect($current)->sort()->keys()->first()];
                break;
            case 2:
                $str2 = "You are most satisfied with " . $you[collect($current)->sortDesc()->keys()->first()] . " and " . $you[collect($current)->sortDesc()->keys()[1]] . ", and least satisfied with " . $you[collect($current)->sort()->keys()->first()];
                break;
            case 3:
                $str2 = "You are most satisfied with " . $you[collect($current)->sortDesc()->keys()->first()] . ", " . $you[collect($current)->sortDesc()->keys()[1]] . " and " . $you[collect($current)->sortDesc()->keys()[2]] . ", and least satisfied with " . $you[collect($current)->sort()->keys()->first()];
                break;
            case 4:
                $str2 = "You are most satisfied with " . $you[collect($current)->sortDesc()->keys()->first()] . ", " . $you[collect($current)->sortDesc()->keys()[1]] . ", " . $you[collect($current)->sortDesc()->keys()[2]] . " and " . $you[collect($current)->sortDesc()->keys()[3]] . ", and least satisfied with " . $you[collect($current)->sort()->keys()->first()];
                break;
            case 5:
                $str = "Virtually <strong>ALL</strong> areas of " . join(' and ', array_filter(array_merge(array(join(', ', array_slice($you, 0, -1))), array_slice($you, -1)), 'strlen')) . " are equally satisfied.";
                break;
        }

        $rank1_gap = $absolute_diffs->sortDesc()->first();
        $rank1_gap_count = $absolute_diffs->filter(function ($res) use ($rank1_gap) {
            return $res == $rank1_gap;
        })->count();

        $str3 = '';
        switch ($rank1_gap_count) {
            case 1:
                $str3 = ". The largest discrepancy between your current satisfaction and importance of these areas is " . $you[$absolute_diffs->sortDesc()->keys()->first()];
                break;
            case 2:
                $str3 = ". The largest discrepancy between your current satisfaction and importance of these areas are " . $you[$absolute_diffs->sortDesc()->keys()->first()] . " and " . $you[$absolute_diffs->sortDesc()->keys()[1]];
                break;
            case 3:
                $str3 = ". The largest discrepancy between your current satisfaction and importance of these areas are " . $you[$absolute_diffs->sortDesc()->keys()->first()] . ", " . $you[$absolute_diffs->sortDesc()->keys()[1]] . " and " . $you[$absolute_diffs->sortDesc()->keys()[2]];
                break;
            case 4:
                $str3 = ". The largest discrepancy between your current satisfaction and importance of these areas are " . $you[$absolute_diffs->sortDesc()->keys()->first()] . ", " . $you[$absolute_diffs->sortDesc()->keys()[1]] . ", " . $you[$absolute_diffs->sortDesc()->keys()[2]] . " and " . $you[$absolute_diffs->sortDesc()->keys()[3]];
                break;
            case 5:
                $str3 = "There are many gaps between where you would like to be and where you feel you currently are";
                break;
        }

        return "Of these five lifestyle areas, " . $str . '. ' . (is_null($user->email) ? " " : "") . $str2 . $str3;
    }

    // Revision March 8, 2021 specs
    public function insight2v3($score, $user): string
    {
        $relative = $score['relative'];
        $current = $score['current'];
        $me = [
            'WORK' => 'My work',
            'MONEY' => '',
            'SOCIETY' => '',
            'SELF' => '',
            'RELATIONSHIPS' => ''
        ];
        $you = [
            'WORK' => '<strong>Your Work</strong>',
            'MONEY' => '<strong>Your Money</strong>',
            'SOCIETY' => '<strong>Society</strong>',
            'SELF' => '<strong>Your Sense of Self</strong>',
            'RELATIONSHIPS' => '<strong>Your Relationships</strong>'
        ];

        $relative_ranked = $this->rank($relative);
        $current_ranked = $this->rank($current);
        $diffs = collect($relative_ranked)->map(function ($value, $key) use ($current_ranked) {
            return $value - $current_ranked[$key];
        });
        $absolute_diffs = collect($diffs)->map(function ($x) {
            return abs($x);
        });
        //return $relative_ranked;
        $rank1_relative = collect($relative)->sortDesc()->first();
        $rank1_relative_count = collect($relative)->filter(function ($res) use ($rank1_relative) {
            return $res == $rank1_relative;
        })->count();
        $str = '';
        switch ($rank1_relative_count) {
            case 1:
                $str = 'The most important aspect in your life is ' . $you[collect($relative)->sortDesc()->keys()->first()] . " and the least important, " . $you[collect($relative)->sort()->keys()->first()];
                break;
            case 2:
                $str = 'The two most important aspects in your life are ' . $you[collect($relative)->sortDesc()->keys()->first()] . " and " . $you[collect($relative)->sortDesc()->keys()[1]] . ", and the least important," . $you[collect($relative)->sort()->keys()->first()];
                break;
            case 3:
                $str = 'The three most important aspects in your life are ' . $you[collect($relative)->sortDesc()->keys()->first()] . ", " . $you[collect($relative)->sortDesc()->keys()[1]] . " and " . $you[collect($relative)->sortDesc()->keys()[2]] . ", and the least important area is " . $you[collect($relative)->sort()->keys()->first()];
                break;
            case 4:
                $str = 'The four most important aspects in your life are ' . $you[collect($relative)->sortDesc()->keys()->first()] . ", " . $you[collect($relative)->sortDesc()->keys()[1]] . ", " . $you[collect($relative)->sortDesc()->keys()[2]] . " and " . $you[collect($relative)->sortDesc()->keys()[3]] . ", and the least important area is " . $you[collect($relative)->sort()->keys()->first()];
                break;
            case 5:
                $str = "Virtually <strong>ALL</strong> areas of " . join(' and ', array_filter(array_merge(array(join(', ', array_slice($you, 0, -1))), array_slice($you, -1)), 'strlen')) . " are equally important.";
                break;
        }

        $rank1_current = collect($current)->sortDesc()->first();
        $rank1_current_count = collect($current)->filter(function ($res) use ($rank1_current) {
            return $res == $rank1_current;
        })->count();
        $str2 = '';
        switch ($rank1_current_count) {
            case 1:
                $str2 = "You are most satisfied with " . $you[collect($current)->sortDesc()->keys()->first()] . ". ";
                break;
            case 2:
                $str2 = "You are most satisfied with " . $you[collect($current)->sortDesc()->keys()->first()] . " and " . $you[collect($current)->sortDesc()->keys()[1]] . ". ";
                break;
            case 3:
                $str2 = "You are most satisfied with " . $you[collect($current)->sortDesc()->keys()->first()] . ", " . $you[collect($current)->sortDesc()->keys()[1]] . " and " . $you[collect($current)->sortDesc()->keys()[2]] . ". ";
                break;
            case 4:
                $str2 = "You are most satisfied with " . $you[collect($current)->sortDesc()->keys()->first()] . ", " . $you[collect($current)->sortDesc()->keys()[1]] . ", " . $you[collect($current)->sortDesc()->keys()[2]] . " and " . $you[collect($current)->sortDesc()->keys()[3]] . ". ";
                break;
            case 5:
                $str = "Virtually <strong>ALL</strong> areas of " . join(' and ', array_filter(array_merge(array(join(', ', array_slice($you, 0, -1))), array_slice($you, -1)), 'strlen')) . " are equally satisfied. ";
                break;
        }

        $rank1_gap = $absolute_diffs->sortDesc()->first();
        $rank1_gap_count = $absolute_diffs->filter(function ($res) use ($rank1_gap) {
            return $res == $rank1_gap;
        })->count();

        $str3 = '';
        switch ($rank1_gap_count) {
            case 1:
                $str3 = $you[$absolute_diffs->sortDesc()->keys()->first()] . " is the most at odds with your current expectations.";
                break;
            case 2:
                $str3 = $you[$absolute_diffs->sortDesc()->keys()->first()] . " and " . $you[$absolute_diffs->sortDesc()->keys()[1]] . " are the most at odds with your current expectations.";
                break;
            case 3:
                $str3 = $you[$absolute_diffs->sortDesc()->keys()->first()] . ", " . $you[$absolute_diffs->sortDesc()->keys()[1]] . " and " . $you[$absolute_diffs->sortDesc()->keys()[2]] . " are the most at odds with your current expectations.";
                break;
            case 4:
                $str3 = $you[$absolute_diffs->sortDesc()->keys()->first()] . ", " . $you[$absolute_diffs->sortDesc()->keys()[1]] . ", " . $you[$absolute_diffs->sortDesc()->keys()[2]] . " and " . $you[$absolute_diffs->sortDesc()->keys()[3]] . " are the most at odds with your current expectations.";
                break;
            case 5:
                $str3 = "There are many gaps between where you would like to be and where you feel you currently are";
                break;
        }

        return $str . '. ' . (is_null($user->email) ? " " : "") . $str2 . $str3;
    }

    public function insight3($score): string
    {
        $relative = collect($score['set1']);
        $current = collect($score['set2']);
        $gaps = $relative->map(function ($value, $key) use ($current) {
            return round($value - $current[$key]);
        });
        $emotions = [
            'SAFETY' => '<strong>a sense of Safety</strong>',
            'CONNECTEDNESS' => '<strong>feelings of Connectedness</strong>',
            'ENJOYMENT' => '<strong>feeling Enjoyment</strong>',
            'EXCITEMENT' => '<strong>feeling Excited</strong>',
            'POWER' => '<strong>feeling YOU have Power</strong>',
            'IN CONTROL' => '<strong>feeling in Control</strong>'
        ];

        $relative_sortedAsc = collect($relative)->sort();
        $relative_sortedDesc = collect($relative)->sortDesc();
        $rank1_relative = $relative_sortedDesc->first();
        $rank1_relative_count = collect($relative)->filter(function ($res) use ($rank1_relative) {
            return $res == $rank1_relative;
        })->count();
        $str = '';
        switch ($rank1_relative_count) {
            case 1:
                $str = "In terms of your emotional fulfilment, the most important aspect for <strong>YOU</strong> is " . $emotions[$relative_sortedDesc->keys()->first()] . " and the least important aspect is " . $emotions[$relative_sortedAsc->keys()->first()];
                break;
            case 2:
                $str = "In terms of your emotional fulfilment, the most important aspect for <strong>YOU</strong> is " . $emotions[$relative_sortedDesc->keys()->first()] . " and " . $emotions[$relative_sortedDesc->keys()[1]] . ", and the least important aspect is " . $emotions[$relative_sortedAsc->keys()->first()];
                break;
            case 3:
                $str = "In terms of your emotional fulfilment, the most important aspect for <strong>YOU</strong> is " . $emotions[$relative_sortedDesc->keys()->first()] . ", " . $emotions[$relative_sortedDesc->keys()[1]] . " and " . $emotions[$relative_sortedDesc->keys()[2]] . ", and the least important aspect is " . $emotions[$relative_sortedAsc->keys()->first()];
                break;
            case 4:
                $str = "In terms of your emotional fulfilment, the most important aspect for <strong>YOU</strong> is " . $emotions[$relative_sortedDesc->keys()->first()] . ", " . $emotions[$relative_sortedDesc->keys()[1]] . ", " . $emotions[$relative_sortedDesc->keys()[2]] . " and " . $emotions[$relative_sortedDesc->keys()[3]] . ", and the least important aspect is " . $emotions[$relative_sortedAsc->keys()->first()];
                break;
            case 5:
                $str = "In terms of your emotional fulfilment, the most important aspect for <strong>YOU</strong> is " . $emotions[$relative_sortedDesc->keys()->first()] . ", " . $emotions[$relative_sortedDesc->keys()[1]] . ", " . $emotions[$relative_sortedDesc->keys()[2]] . ", " . $emotions[$relative_sortedDesc->keys()[3]] . " and " . $emotions[$relative_sortedDesc->keys()[4]] . ", and the least important aspect is " . $emotions[$relative_sortedAsc->keys()->first()];
                break;
            case 6:
                $str = "Virtually <strong>ALL</strong> areas of " . join(' and ', array_filter(array_merge(array(join(', ', array_slice($emotions, 0, -1))), array_slice($emotions, -1)), 'strlen')) . " are equally important";
                break;
        }

        $current_sortedAsc = collect($current)->sort();
        $current_sortedDesc = collect($current)->sortDesc();
        $rank1_current = $current_sortedDesc->first();
        $rank1_current_count = collect($current)->filter(function ($res) use ($rank1_current) {
            return $res == $rank1_current;
        })->count();
        $str2 = '';
        switch ($rank1_current_count) {
            case 1:
                $str2 = "Currently, <strong>YOU</strong> are most satisfied with " . $emotions[$current_sortedDesc->keys()->first()] . " and least satisfied with " . $emotions[$current_sortedAsc->keys()->first()];
                break;
            case 2:
                $str2 = "Currently, <strong>YOU</strong> are most satisfied with " . $emotions[$current_sortedDesc->keys()->first()] . " and " . $emotions[$current_sortedDesc->keys()[1]] . ", and least satisfied with " . $emotions[$current_sortedAsc->keys()->first()];
                break;
            case 3:
                $str2 = "Currently, <strong>YOU</strong> are most satisfied with " . $emotions[$current_sortedDesc->keys()->first()] . ", " . $emotions[$current_sortedDesc->keys()[1]] . " and " . $emotions[$current_sortedDesc->keys()[2]] . ", and least satisfied with " . $emotions[$current_sortedAsc->keys()->first()];
                break;
            case 4:
                $str2 = "Currently, <strong>YOU</strong> are most satisfied with " . $emotions[$current_sortedDesc->keys()->first()] . ", " . $emotions[$current_sortedDesc->keys()[1]] . ", " . $emotions[$current_sortedDesc->keys()[2]] . " and " . $emotions[$current_sortedDesc->keys()[3]] . ", and least satisfied with " . $emotions[$current_sortedAsc->keys()->first()];
                break;
            case 5:
                $str2 = "Currently, <strong>YOU</strong> are most satisfied with " . $emotions[$current_sortedDesc->keys()->first()] . ", " . $emotions[$current_sortedDesc->keys()[1]] . ", " . $emotions[$current_sortedDesc->keys()[2]] . ", " . $emotions[$current_sortedDesc->keys()[3]] . " and " . $emotions[$current_sortedDesc->keys()[4]] . ", and least satisfied with " . $emotions[$current_sortedAsc->keys()->first()];
                break;
            case 6:
                $str2 = "Virtually <strong>ALL</strong> areas of " . join(' and ', array_filter(array_merge(array(join(', ', array_slice($emotions, 0, -1))), array_slice($emotions, -1)), 'strlen')) . " are equally satisfied";
                break;
        }

        $gaps_sortedAsc = collect($gaps)->sort();
        $gaps_sortedDesc = collect($gaps)->sortDesc();
        $gaps_rank1 = $gaps_sortedDesc->first();
        $gaps_rank1_count = collect($gaps)->filter(function ($res) use ($gaps_rank1) {
            return $res == $gaps_rank1;
        })->count();

        $str3 = "";
        switch ($gaps_rank1_count) {
            case 1:
                $str3 = "While the largest gap between your ideal sense of emotional fulfilment and your current levels of satisfaction is with " . $emotions[$gaps_sortedDesc->keys()->first()];
                break;
            case 2:
                $str3 = "While the largest gap between your ideal sense of emotional fulfilment and your current levels of satisfaction is with " . $emotions[$gaps_sortedDesc->keys()->first()] . " and " . $emotions[$gaps_sortedDesc->keys()[1]];
                break;
            case 3:
                $str3 = "While the largest gap between your ideal sense of emotional fulfilment and your current levels of satisfaction is with " . $emotions[$gaps_sortedDesc->keys()->first()] . ", " . $emotions[$gaps_sortedDesc->keys()[1]] . " and " . $emotions[$gaps_sortedDesc->keys()[2]];
                break;
            case 4:
                $str3 = "While the largest gap between your ideal sense of emotional fulfilment and your current levels of satisfaction is with " . $emotions[$gaps_sortedDesc->keys()->first()] . ", " . $emotions[$gaps_sortedDesc->keys()[1]] . ", " . $emotions[$gaps_sortedDesc->keys()[2]] . " and " . $emotions[$gaps_sortedDesc->keys()[3]];
                break;
            case 5:
                $str3 = "While the largest gap between your ideal sense of emotional fulfilment and your current levels of satisfaction is with " . $emotions[$gaps_sortedDesc->keys()->first()] . ", " . $emotions[$gaps_sortedDesc->keys()[1]] . ", " . $emotions[$gaps_sortedDesc->keys()[2]] . ", " . $emotions[$gaps_sortedDesc->keys()[3]] . " and " . $emotions[$gaps_sortedDesc->keys()[4]];
                break;
            case 6:
                $str3 = "There are many gaps between where you would like to be and where you feel you currently are";
                break;
        }

        $gaps_rank_last = $gaps_sortedAsc->first();
        $gaps_rank_last_count = collect($gaps)->filter(function ($res) use ($gaps_rank_last) {
            return $res == $gaps_rank_last;
        })->count();
        $str4 = "";
        switch ($gaps_rank_last_count) {
            case 1:
                $str4 = ", the smallest gap is " . $emotions[$gaps_sortedAsc->keys()->first()];
                break;
            case 2:
                $str4 = ", the smallest gap are " . $emotions[$gaps_sortedAsc->keys()->first()] . " and " . $emotions[$gaps_sortedAsc->keys()[1]];
                break;
            case 3:
                $str4 = ", the smallest gap are " . $emotions[$gaps_sortedAsc->keys()->first()] . ", " . $emotions[$gaps_sortedAsc->keys()[1]] . " and " . $emotions[$gaps_sortedAsc->keys()[2]];
                break;
            case 4:
                $str4 = ", the smallest gap are " . $emotions[$gaps_sortedAsc->keys()->first()] . ", " . $emotions[$gaps_sortedAsc->keys()[1]] . ", " . $emotions[$gaps_sortedAsc->keys()[2]] . " and " . $emotions[$gaps_sortedAsc->keys()[3]];
                break;
            case 5:
                $str4 = ", the smallest gap are " . $emotions[$gaps_sortedAsc->keys()->first()] . ", " . $emotions[$gaps_sortedAsc->keys()[1]] . ", " . $emotions[$gaps_sortedAsc->keys()[2]] . ", " . $emotions[$gaps_sortedAsc->keys()[3]] . " and " . $emotions[$gaps_sortedAsc->keys()[4]];
                break;
        }

        return "{$str}.<br><br>{$str2}. {$str3}{$str4}";
    }

    // Revision March 8, 2021
    public function insight3v2($score): string
    {
        $relative = collect($score['set1']);
        $current = collect($score['set2']);
        $gaps = $relative->map(function ($value, $key) use ($current) {
            return round($value - $current[$key]);
        });
        $emotions = [
            'SAFETY' => '<strong>a sense of Safety</strong>',
            'CONNECTEDNESS' => '<strong>feeling of Connectedness</strong>',
            'ENJOYMENT' => '<strong>feeling Enjoyment</strong>',
            'EXCITEMENT' => '<strong>feeling Excited</strong>',
            'POWER' => '<strong>having Power</strong>',
            'IN CONTROL' => '<strong>feeling in Control</strong>'
        ];

        $relative_sortedAsc = collect($relative)->sort();
        $relative_sortedDesc = collect($relative)->sortDesc();
        $rank1_relative = $relative_sortedDesc->first();
        $rank1_relative_count = collect($relative)->filter(function ($res) use ($rank1_relative) {
            return $res == $rank1_relative;
        })->count();
        $str = '';
        switch ($rank1_relative_count) {
            case 1:
                $str = "Your most important emotion is " . $emotions[$relative_sortedDesc->keys()->first()] . " and the least important for you is " . $emotions[$relative_sortedAsc->keys()->first()];
                break;
            case 2:
                $str = "Your most important emotions are " . $emotions[$relative_sortedDesc->keys()->first()] . " and " . $emotions[$relative_sortedDesc->keys()[1]] . ", and the least important for you is " . $emotions[$relative_sortedAsc->keys()->first()];
                break;
            case 3:
                $str = "Your most important emotions are " . $emotions[$relative_sortedDesc->keys()->first()] . ", " . $emotions[$relative_sortedDesc->keys()[1]] . " and " . $emotions[$relative_sortedDesc->keys()[2]] . ", and the least important for you is " . $emotions[$relative_sortedAsc->keys()->first()];
                break;
            case 4:
                $str = "Your most important emotions are " . $emotions[$relative_sortedDesc->keys()->first()] . ", " . $emotions[$relative_sortedDesc->keys()[1]] . ", " . $emotions[$relative_sortedDesc->keys()[2]] . " and " . $emotions[$relative_sortedDesc->keys()[3]] . ", and the least important for you is " . $emotions[$relative_sortedAsc->keys()->first()];
                break;
            case 5:
                $str = "Your most important emotions are " . $emotions[$relative_sortedDesc->keys()->first()] . ", " . $emotions[$relative_sortedDesc->keys()[1]] . ", " . $emotions[$relative_sortedDesc->keys()[2]] . ", " . $emotions[$relative_sortedDesc->keys()[3]] . " and " . $emotions[$relative_sortedDesc->keys()[4]] . ", and the least important for you is " . $emotions[$relative_sortedAsc->keys()->first()];
                break;
            case 6:
                $str = "Virtually <strong>ALL</strong> areas of " . join(' and ', array_filter(array_merge(array(join(', ', array_slice($emotions, 0, -1))), array_slice($emotions, -1)), 'strlen')) . " are equally important";
                break;
        }

        $current_sortedAsc = collect($current)->sort();
        $current_sortedDesc = collect($current)->sortDesc();
        $rank1_current = $current_sortedDesc->first();
        $rank1_current_count = collect($current)->filter(function ($res) use ($rank1_current) {
            return $res == $rank1_current;
        })->count();
        $str2 = '';
        $emotions2 = [
            'SAFETY' => 'Safe',
            'CONNECTEDNESS' => 'Connected',
            'ENJOYMENT' => 'Enjoyment',
            'EXCITEMENT' => 'Excitement',
            'POWER' => 'that you have Power',
            'IN CONTROL' => 'that you are In Control'
        ];
        $emotions3 = [
            'SAFETY' => 'Safety',
            'CONNECTEDNESS' => 'Connected',
            'ENJOYMENT' => 'Enjoyment',
            'EXCITEMENT' => 'Excitement',
            'POWER' => 'Power',
            'IN CONTROL' => 'In Control'
        ];
        $emotions4 = [
            'SAFETY' => 'Safety',
            'CONNECTEDNESS' => 'Connectedness',
            'ENJOYMENT' => 'Enjoyment',
            'EXCITEMENT' => 'Excitement',
            'POWER' => 'having Power',
            'IN CONTROL' => 'being In Control'
        ];



        switch ($rank1_current_count) {
            case 1:
                $str2 = "Currently, you feel " . $emotions2[$current_sortedDesc->keys()->first()] . " but not much " . $emotions3[$current_sortedAsc->keys()->first()] . ".";
                break;
            case 2:
                $str2 = "Currently, you feel " . $emotions2[$current_sortedDesc->keys()->first()] . " and " . $emotions2[$current_sortedDesc->keys()[1]] . " but not much " . $emotions3[$current_sortedAsc->keys()->first()] . ".";
                break;
            case 3:
                $str2 = "Currently, you feel " . $emotions2[$current_sortedDesc->keys()->first()] . ", " . $emotions2[$current_sortedDesc->keys()[1]] . " and " . $emotions2[$current_sortedDesc->keys()[2]] . " but not much " . $emotions3[$current_sortedAsc->keys()->first()] . ".";
                break;
            case 4:
                $str2 = "Currently, you feel " . $emotions2[$current_sortedDesc->keys()->first()] . ", " . $emotions2[$current_sortedDesc->keys()[1]] . ", " . $emotions2[$current_sortedDesc->keys()[2]] . " and " . $emotions2[$current_sortedDesc->keys()[3]] . " but not much " . $emotions3[$current_sortedAsc->keys()->first()] . ".";
                break;
            case 5:
                $str2 = "Currently, you feel " . $emotions2[$current_sortedDesc->keys()->first()] . ", " . $emotions2[$current_sortedDesc->keys()[1]] . ", " . $emotions2[$current_sortedDesc->keys()[2]] . ", " . $emotions2[$current_sortedDesc->keys()[3]] . " and " . $emotions2[$current_sortedDesc->keys()[4]] . " but not much " . $emotions3[$current_sortedAsc->keys()->first()] . ".";
                break;
            case 6:
                $str2 = "Virtually <strong>ALL</strong> areas of " . join(' and ', array_filter(array_merge(array(join(', ', array_slice($emotions, 0, -1))), array_slice($emotions, -1)), 'strlen')) . " are equally satisfied";
                break;
        }

        $gaps_sortedAsc = collect($gaps)->sort();
        $gaps_sortedDesc = collect($gaps)->sortDesc();
        $gaps_rank1 = $gaps_sortedDesc->first();
        $gaps_rank1_count = collect($gaps)->filter(function ($res) use ($gaps_rank1) {
            return $res == $gaps_rank1;
        })->count();

        $str3 = "";
        switch ($gaps_rank1_count) {
            case 1:
                $str3 = "So, your sense of " . $emotions4[$gaps_sortedDesc->keys()->first()] . " falls well short of your hopes and expectation.";
                break;
            case 2:
                $str3 = "So, your sense of " . $emotions4[$gaps_sortedDesc->keys()->first()] . " and " . $emotions4[$gaps_sortedDesc->keys()[1]] . " falls well short of your hopes and expectation.";
                break;
            case 3:
                $str3 = "So, your sense of " . $emotions4[$gaps_sortedDesc->keys()->first()] . ", " . $emotions4[$gaps_sortedDesc->keys()[1]] . " and " . $emotions4[$gaps_sortedDesc->keys()[2]] . " falls well short of your hopes and expectation.";
                break;
            case 4:
                $str3 = "So, your sense of " . $emotions4[$gaps_sortedDesc->keys()->first()] . ", " . $emotions4[$gaps_sortedDesc->keys()[1]] . ", " . $emotions4[$gaps_sortedDesc->keys()[2]] . " and " . $emotions4[$gaps_sortedDesc->keys()[3]] . " falls well short of your hopes and expectation.";
                break;
            case 5:
                $str3 = "So, your sense of " . $emotions4[$gaps_sortedDesc->keys()->first()] . ", " . $emotions4[$gaps_sortedDesc->keys()[1]] . ", " . $emotions4[$gaps_sortedDesc->keys()[2]] . ", " . $emotions4[$gaps_sortedDesc->keys()[3]] . " and " . $emotions4[$gaps_sortedDesc->keys()[4]] . " falls well short of your hopes and expectation.";
                break;
            case 6:
                $str3 = "There are many gaps between where you would like to be and where you feel you currently are";
                break;
        }
        // info($relative_sortedDesc->toArray());
        // info($current_sortedDesc->toArray());
        // info($current_sortedAsc->toArray());
        // info($gaps_sortedDesc->toArray());
        $gaps_rank_last = $gaps_sortedAsc->first();
        $gaps_rank_last_count = collect($gaps)->filter(function ($res) use ($gaps_rank_last) {
            return $res == $gaps_rank_last;
        })->count();
        $str4 = "";
        switch ($gaps_rank_last_count) {
            case 1:
                $str4 = ", the smallest gap is " . $emotions[$gaps_sortedAsc->keys()->first()];
                break;
            case 2:
                $str4 = ", the smallest gap are " . $emotions[$gaps_sortedAsc->keys()->first()] . " and " . $emotions[$gaps_sortedAsc->keys()[1]];
                break;
            case 3:
                $str4 = ", the smallest gap are " . $emotions[$gaps_sortedAsc->keys()->first()] . ", " . $emotions[$gaps_sortedAsc->keys()[1]] . " and " . $emotions[$gaps_sortedAsc->keys()[2]];
                break;
            case 4:
                $str4 = ", the smallest gap are " . $emotions[$gaps_sortedAsc->keys()->first()] . ", " . $emotions[$gaps_sortedAsc->keys()[1]] . ", " . $emotions[$gaps_sortedAsc->keys()[2]] . " and " . $emotions[$gaps_sortedAsc->keys()[3]];
                break;
            case 5:
                $str4 = ", the smallest gap are " . $emotions[$gaps_sortedAsc->keys()->first()] . ", " . $emotions[$gaps_sortedAsc->keys()[1]] . ", " . $emotions[$gaps_sortedAsc->keys()[2]] . ", " . $emotions[$gaps_sortedAsc->keys()[3]] . " and " . $emotions[$gaps_sortedAsc->keys()[4]];
                break;
        }

        return "{$str}.<br><br>{$str2} {$str3}";
    }

    /*
     * End revision
     */

    private function whatIndex($value): string
    {
        if ($value > 0) {
            return 'a';
        } else if ($value < 0) {
            return 'b';
        } else {
            return 'c';
        }
    }

    private function getRanking($score): array
    {
        $ranked_values = collect($score)->unique()->sort()->values()->flip()->map(function ($x) {
            return $x + 1;
        })->toArray();
        return collect($score)->map(function ($x) use ($ranked_values) {
            return $ranked_values[$x];
        })->toArray();
    }

    private function rank($scores): array
    {
        # Keep input array "scores" and replace values with rank.
        # This preserves the order. Working on a copy called $scores
        # to set the ranks.
        $x = collect($scores)->toArray();
        arsort($x);
        # Initival values
        $rank       = 0;
        $hiddenrank = 0;
        $hold = null;
        foreach ($x as $key => $val) {
            # Always increase hidden rank
            $hiddenrank += 1;
            # If current value is lower than previous:
            # set new hold, and set rank to hiddenrank.
            if (is_null($hold) || $val < $hold) {
                $rank = $hiddenrank;
                $hold = $val;
            }
            # Set rank $rank for $scores[$key]
            $scores[$key] = $rank;
        }
        return $scores;
    }

    private function replaceZ($phase2): array
    {
        return [
            'relative' => $this->replaceZero($phase2['relative']),
            'current' => $this->replaceZero($phase2['current'])
        ];
    }

    private function replaceZero($data): array
    {
        if (collect($data)->contains(0)) {
            $d = collect($data)->filter(function ($x) {
                return $x <> 0;
            });
            $lowestNonZero = $d->sort()->first();
            $replaceValue = round($lowestNonZero / 2, 0);
            return collect($data)->map(function ($v) use ($replaceValue) {
                return $v === 0 ? $replaceValue : $v;
            })->toArray();
        } else {
            return $data;
        }
    }

    private function insight2($sc): string
    {
        $score = $sc['relative'];
        $lowest = collect($sc['current'])->sort()->keys()->first();
        $highest = collect($score)->sortDesc()->keys()->first();
        $name = [
            'WORK' => 'Your Work',
            'SELF' => 'Your Self',
            'SOCIETY' => 'Society',
            'MONEY' => 'Your Money',
            'RELATIONSHIPS' => 'Your Relationships'
        ];
        return "Your lifestyle motivators show that the most important area to you in feeling fulfilled is <strong>{$name[$highest]}</strong> and that <strong>YOU</strong> are currently least fulfilled with <strong>{$name[$lowest]}</strong>";
    }

    private function phaseA($score, $group = 'work'): array
    {
        if (is_null($score))
            return [
                'data' => [],
                'insight' => ''
            ];
        $indexes = [];
        switch ($group) {
            case 'work':
                $indexes = [
                    'promotion and career development',
                    'fair pay',
                    'flexible working',
                    'childcare provision',
                    'return to work / maternity support',
                    'support from leadership and management',
                    'network and mentor support'
                ];
                break;
            case 'self':
                $indexes = [
                    'my confidence',
                    'my resilience',
                    'that I look and feel good',
                    'my mental wellbeing',
                    'my role as a mother',
                    'my impact at work',
                    'my role as a wife/partner',
                    'my role as a friend',
                    'my contribution to society/community'
                ];
                break;
            case 'society':
                $indexes = [
                    'gender equality',
                    'female representation in media',
                    'female representation in my industry',
                    'ending ethnic/religious discrimination',
                    'improving social equality (poverty/ education)',
                    'protecting the environment'
                ];
                break;
            case 'money':
                $indexes = [
                    'my salary',
                    'my future earning potential',
                    'having manageable debt',
                    'economic independence',
                    'financial security',
                    'living within my means',
                    'building investments for the future',
                    'making financial savings'
                ];
                break;
            case 'relationships':
                $indexes = [
                    'my partner / spouse',
                    'my parents',
                    'my friends',
                    'my colleagues',
                    'my children',
                    'my boss'
                ];
                break;
        }

        $result = [];
        foreach (generator($indexes) as $k => $index) {
            $score1 = $this->replaceLessThanTen($score['set1'][$index]);
            $score2 = $this->replaceLessThanTen($score['set2'][$index]);
            $result[] = [
                'id' => $k + 1,
                'name' => $index,
                'v1' => $score1,
                'v2' => $score2,
                'diff' => $score1 - $score2
            ];
        }
        $result = collect($result)->sortByDesc('v1')->values()->toArray();
        $pillars = [
            'work' => 'My Work',
            'self' => 'My Self',
            'society' => 'Society',
            'money' => 'My Money',
            'relationships' => 'My Relationships'
        ];
        $diff = collect($result)->sortByDesc('diff')->values()->toArray();

        $rank1_gap = collect($result)->sortByDesc('diff')->first()['diff'];
        $rank1_gap_count = collect($result)->filter(function ($res) use ($rank1_gap) {
            return $res['diff'] == $rank1_gap;
        })->count();
        $str = '';
        switch ($rank1_gap_count) {
            case 1:
                $str = ". Your biggest gap is with " . strtoupper($diff[0]['name']);
                break;
            case 2:
                $str = ". Your biggest gap is with " . strtoupper($diff[0]['name']) . " and " . strtoupper($diff[1]['name']);
                break;
            case 3:
                $str = ". Your biggest gap is with " . strtoupper($diff[0]['name']) . ", " . strtoupper($diff[1]['name']) . " and " . strtoupper($diff[2]['name']);
                break;
            case 4:
                $str = ". Your biggest gap is with " . strtoupper($diff[0]['name']) . ", " . strtoupper($diff[1]['name']) . ", " . strtoupper($diff[2]['name']) . " and " . strtoupper($diff[3]['name']);
                break;
            case 5:
            case 6:
            case 7:
            case 8:
            case 9:
                $str = "There are many gaps between where <strong>YOU</strong> would like to be and where <strong>YOU</strong> feel <strong>YOU</strong> currently are";
                break;
        }
        $foot = [
            'work' => 'at Work',
            'self' => 'in Your Self',
            'society' => 'with Society',
            'money' => 'in terms of Your Money',
            'relationships' => 'in Your Relationships'
        ];
        $text = "The most important aspect of feeling fulfilled with {$pillars[$group]} is " . strtoupper($result[0]['name']) . ", followed by " . strtoupper($result[1]['name']) . $str . ".<br><br><p>If you’d like to discover more about ways you can improve your Fulfilment {$foot[$group]}, why not take a look at some of the recommended content here: <a href='https://www.thefemalelead.com/{$group}' target='_blank'>https://www.thefemalelead.com/{$group}</a></p>";

        return [
            'data' => $result,
            'insight' => $text
        ];
    }

    private function replaceLessThanTen($value): int
    {
        return $value < 10 ? 10 : $value;
    }

    private function phaseB($score, $group = 'work'): array
    {
        if (is_null($score))
            return [
                'data' => [],
                'insight' => ''
            ];
        $indexes = [
            'SAFETY',
            'CONNECTEDNESS',
            'ENJOYMENT',
            'EXCITEMENT',
            'POWER',
            'IN CONTROL'
        ];
        $result = [];
        foreach (generator($indexes) as $k => $index) {
            $score1 = $this->replaceLessThanTen($score['set1'][$index]);
            $score2 = $this->replaceLessThanTen($score['set2'][$index]);
            $result[] = [
                'id' => $k + 1,
                'name' => $index,
                'v1' => $score1,
                'v2' => $score2,
                'diff' => $score1 - $score2
            ];
        }
        $ranked = collect($result)->sortByDesc('v1')->values()->toArray();
        $pillars = [
            'work' => 'My Work',
            'self' => 'My Self',
            'society' => 'Society',
            'money' => 'My Money',
            'relationships' => 'My Relationships'
        ];

        $diff = collect($result)->sortByDesc('diff')->values()->toArray();
        $rank1 = $ranked[0]['v1'];
        $rank1_count = collect($result)->filter(function ($res) use ($rank1) {
            return $res['v1'] == $rank1;
        })->count();
        switch ($rank1_count) {
            case 1:
                $text1 = "The most important emotions that drive fulfilment in the area of {$pillars[$group]} are in " . strtoupper($ranked[0]['name']) . ", followed by " . strtoupper($ranked[1]['name']) . ". ";
                break;
            case 2:
                $text1 = "The two most important emotions that drive fulfilment in the area of {$pillars[$group]} are in " . strtoupper($ranked[0]['name']) . " and " . strtoupper($ranked[1]['name']) . ", followed by " . strtoupper($ranked[2]['name']) . ". ";
                break;
            case 3:
                $text1 = "The three most important emotions that drive fulfilment in the area of {$pillars[$group]} are in " . strtoupper($ranked[0]['name']) . ", " . strtoupper($ranked[1]['name']) . " and " . strtoupper($ranked[2]['name']) . ", followed by " . strtoupper($ranked[3]['name']) . ". ";
                break;
            case 4:
                $text1 = "The four most important emotions that drive fulfilment in the area of {$pillars[$group]} are in " . strtoupper($ranked[0]['name']) . ", " . strtoupper($ranked[1]['name']) . ", " . strtoupper($ranked[2]['name']) . " and " . strtoupper($ranked[3]['name']) . ", followed by " . strtoupper($ranked[4]['name']) . ". ";
                break;
            case 5:
                $text1 = "The five most important emotions that drive fulfilment in the area of {$pillars[$group]} are in " . strtoupper($ranked[0]['name']) . ", " . strtoupper($ranked[1]['name']) . ", " . strtoupper($ranked[2]['name']) . ", " . strtoupper($ranked[3]['name']) . " and " . strtoupper($ranked[4]['name']) . ", followed by " . strtoupper($ranked[5]['name']) . ". ";
                break;
        }
        $maxf = collect($result)->pluck('v1')->toArray();
        //$text1 = "The most important emotions that drive fulfilment in the area of {$pillars[$group]} are in " . strtoupper($ranked[0]['name']) . ", followed by " . strtoupper($ranked[1]['name']) . ". ";
        if (count(array_unique($maxf)) === 1) {
            $text1 = "All emotional dimensions are equally important in driving fulfilment in the area of {$pillars[$group]}. ";
        }

        $d = collect($diff)->pluck('diff')->toArray();
        $text2 = "Your biggest gap is with " . strtoupper($diff[0]['name']) . ($diff[0]['diff'] == $diff[1]['diff'] ? (" and " . strtoupper($diff[1]['name']) . ".") : ".");
        if ($d[0] === 0 && count(array_unique($d)) === 1) {
            $text2 = "There are no gaps between where <strong>YOU</strong> would like to be and where <strong>YOU</strong> are now.";
        } else if (count(array_unique($d)) === 1) {
            $text2 = "The gaps between where <strong>YOU</strong> would like to be and where <strong>YOU</strong> are now are the same across all emotional dimensions";
        }
        $text = $text1 . $text2 . "<br><br><p>To discover more about the different emotional motivators and how they drive your sense of Fulfilment, why not take a look at some of the recommended content here: <a href='https://www.thefemalelead.com' target='_blank'>https://www.thefemalelead.com</a</p>";

        //$text = "The most important emotions that drive fulfilment in the area of {$pillars[$group]} are in " . strtoupper($ranked[0]['name']) . ", followed by " . strtoupper($ranked[1]['name']) . ". Your biggest gap is in " . strtoupper($diff[0]['name']) . ($diff[0]['diff'] == $diff[1]['diff'] ? (" and " . strtoupper($diff[1]['name']) . ".") : ".");
        return [
            'data' => $result,
            'insight' => $text
        ];
    }

    private function phase2($score): array
    {
        $indexes = [
            'WORK',
            'SELF',
            'SOCIETY',
            'MONEY',
            'RELATIONSHIPS'
        ];
        $name = [
            'WORK' => 'My Work',
            'SELF' => 'My Self',
            'SOCIETY' => 'Society',
            'MONEY' => 'My Money',
            'RELATIONSHIPS' => 'My Relationships'
        ];
        $results = [];
        foreach (generator($indexes) as $k => $index) {
            $results[] = [
                'id' => $k + 1,
                'name' => $name[$index],
                'v1' => $score['relative'][$index],
                'v2' => $score['current'][$index]
            ];
        }
        $results = collect($results)->sortByDesc('v1')->values()->toArray();
        if ($results[0]['v1'] == $results[1]['v1']) {
            $v1 = $results[0];
            $v2 = $results[1];
            if ($v1['v2'] > $v2['v2']) {
                $results[0] = $v1;
                $results[1] = $v2;
            } else {
                $results[0] = $v2;
                $results[1] = $v1;
            }
        }
        if ($results[1]['v1'] == $results[2]['v1']) {
            $v1 = $results[1];
            $v2 = $results[2];
            if ($v1['v2'] > $v2['v2']) {
                $results[1] = $v1;
                $results[2] = $v2;
            } else {
                $results[1] = $v2;
                $results[2] = $v1;
            }
        }
        if ($results[2]['v1'] == $results[3]['v1']) {
            $v1 = $results[2];
            $v2 = $results[3];
            if ($v1['v2'] > $v2['v2']) {
                $results[2] = $v1;
                $results[3] = $v2;
            } else {
                $results[2] = $v2;
                $results[3] = $v1;
            }
        }
        if ($results[3]['v1'] == $results[4]['v1']) {
            $v1 = $results[3];
            $v2 = $results[4];
            if ($v1['v2'] > $v2['v2']) {
                $results[3] = $v1;
                $results[4] = $v2;
            } else {
                $results[3] = $v2;
                $results[4] = $v1;
            }
        }
        if (count(array_unique(collect($results)->pluck('v1')->toArray())) === 1) {
            $results = collect($results)->sortByDesc('v2')->values()->toArray();
        }
        return $results;
    }

    private function phase4($score, $user): string
    {
        $base_scores = $score;
        $score = collect($score)->sortDesc()->keys();
        $phase4_char = [
            'THE CONNECTOR' => 'a Connector',
            'THE ALPHA FEMALE' => 'an Alpha Female',
            'THE PLEASURE SEEKER' => 'a Pleasure Seeker',
            'THE ORGANISER' => 'an Organiser'
        ];
        $phase4_desc = [
            'THE CONNECTOR' => '<strong>The Connector</strong> enjoys being with people and bringing people together. She is a ‘social animal’ who always seeks to get involved with activities with her peers and this contributes to her need to feel valued. She seeks to support and comfort others and to be supported and comforted in return. The Connector wants to feel like she belongs and so is most likely to be a member of a group or club. She loves social gatherings and seldom turns down invitations to get-togethers because when she feels connected she also feels safe and secure.',
            'THE ALPHA FEMALE' => '<strong>The Alpha Female</strong> makes a great leader. She is confident and strong. As an independent person she gets her strength from feeling, and being seen to be, successful and influential. But there is a creative side to her as well; boredom and stagnation are her enemies and so she needs constant stimulation, and she can easily become inspired and energised by an idea. She is happiest when she is championing a cause or solving a problem, the more adventurous the cause or problem the better.',
            'THE PLEASURE SEEKER' => '<strong>The Pleasure Seeker</strong> is a hedonist – she seeks pleasure and tries to avoid pain. She is an extravert, and for her, the pursuit of happiness is equated with entertaining others as well as being entertained by others. Indeed, she feels at her best when she is laughing, having fun and just having a mad time. Her enjoyment in the sensory pleasures in life are driven by the need to feel carefree, relaxed, and calm and by the desire to avoid feelings of anxiety and worry at all cost. This girl just wants to have fun.',
            'THE ORGANISER' => '<strong>The Organiser</strong> seeks control over her life. She is more introverted than extraverted and seeks an uncomplicated life. She is very organised, noticeably so as things in her home and office are seldom out of place. But it is not just neatness she seeks, she is a very practical person who can survive very difficult situations, the sort of person who would easily survive if she found herself stranded in the jungle – people would turn to her to get them out of trouble. She probably loves DIY or re-designing her home or workplace.'
        ];
        $persona_char = [
            "INFLUENCER" => 'an <strong>INFLUENCER</strong>',
            "EVERYONE'S FRIEND" => '<strong>EVERYONE\'S FRIEND</strong>',
            "MEDIATOR" => 'a <strong>MEDIATOR</strong>',
            "BUSINESS EXECUTIVE" => 'a <strong>BUSINESS EXECUTIVE</strong>',
            "REFORMER" => 'a <strong>REFORMER</strong>',
            "INVESTIGATOR" => 'an <strong>INVESTIGATOR</strong>',
            "ENTERTAINER" => 'an <strong>ENTERTAINER</strong>',
            "BON VIVANT" => 'a <strong>BON VIVANT</strong>',
            "CREATOR" => 'a <strong>CREATOR</strong>',
            "PEACEMAKER" => 'a <strong>PEACEMAKER</strong>',
            "CRISIS MANAGER" => 'a <strong>CRISIS MANAGER</strong>',
            "KNOWLEDGE SEEKER" => 'a <strong>KNOWLEDGE SEEKER</strong>'
        ];
        $persona_combination = [
            "INFLUENCER" => ["THE CONNECTOR", "THE ALPHA FEMALE"],
            "EVERYONE'S FRIEND" => ["THE CONNECTOR", "THE PLEASURE SEEKER"],
            "MEDIATOR" => ["THE CONNECTOR", "THE ORGANISER"],
            "BUSINESS EXECUTIVE" => ["THE ALPHA FEMALE", "THE CONNECTOR"],
            "REFORMER" => ["THE ALPHA FEMALE", "THE PLEASURE SEEKER"],
            "INVESTIGATOR" => ["THE ALPHA FEMALE", "THE ORGANISER"],
            "ENTERTAINER" => ["THE PLEASURE SEEKER", "THE CONNECTOR"],
            "BON VIVANT" => ["THE PLEASURE SEEKER", "THE ALPHA FEMALE"],
            "CREATOR" => ["THE PLEASURE SEEKER", "THE ORGANISER"],
            "PEACEMAKER" => ["THE ORGANISER", "THE CONNECTOR"],
            "CRISIS MANAGER" => ["THE ORGANISER", "THE ALPHA FEMALE"],
            "KNOWLEDGE SEEKER" => ["THE ORGANISER", "THE PLEASURE SEEKER"]
        ];

        $persona_id = [
            "INFLUENCER" => 1,
            "EVERYONE'S FRIEND" => 2,
            "MEDIATOR" => 3,
            "BUSINESS EXECUTIVE" => 4,
            "REFORMER" => 5,
            "INVESTIGATOR" => 6,
            "ENTERTAINER" => 7,
            "BON VIVANT" => 8,
            "CREATOR" => 9,
            "PEACEMAKER" => 10,
            "CRISIS MANAGER" => 11,
            "KNOWLEDGE SEEKER" => 12
        ];

        $res = [$score[0], $score[1]];
        $persona = collect($persona_combination)->filter(function ($v) use ($res) {
            return $v === $res;
        })->keys()[0];

        $all_count = Counter::whereNotNull('meta')->with('user')->has('user')->count();
        $persona_count = Counter::where('persona->result', $persona)->whereNotNull('meta')->with('user')->has('user')->count();
        $percent = round(($persona_count / $all_count) * 100, 0);

        $persona_descriptions = [
            "INFLUENCER" => '
                <div class="name">
                    INFLUENCER
                </div>
                <div class="sDesc">
                    When I am feeling fulfilled, I am a Connector and a Leader.
                </div>
                <div class="fDesc">
                    <div>
                        <strong>The INFLUENCER:</strong>
                    </div>
                    <p><br><strong>' . $percent . '%</strong> of women who have taken the survey have the same PERSONA as you.<br><br><em>(Connector:Leader)</em>  enjoys being with people and bringing them together. Socially, she is a busy bee, highly visible and approachable. The Influencer has confidence in her own abilities and a lot of people look up to her. She may start up her own group or organisation and then bring other like-minded people with her.
                    </p>
                    <p>
                    The Influencer keeps moving and creating and won’t stand still for long. She can use her experience to encourage others to be open to possibilities and to find out what will and will not work for them.
                    </p>
                </div>',
            "EVERYONE'S FRIEND" => '
                <div class="name">
                    EVERYONE\'S FRIEND
                </div>
                <div class="sDesc">
                    When I am feeling fulfilled, I am a Connector and a Joy Seeker.
                </div>
                <div class="fDesc">
                    <div>
                        <strong>EVERYONE\'S FRIEND:</strong>
                    </div>
                    <p><br><strong>' . $percent . '%</strong> of women who have taken the survey have the same PERSONA as you.<br><br><em>(Connector:Joy Seeker)</em>  loves social gatherings and seldom turns down invitations to get-togethers because when she feels connected, she also feels safe and secure. She wants to feel happy and loves to see others happy too. As someone with super listening skills and a desire to really understand how others think and feel, she makes a great team builder.
                    </p>
                    <p>
                    Indeed, she feels at her best when she is laughing, having fun and just having a mad time. She can use her experience to let others realise that one should lead by looking after one’s team, and that by doing that they can have the best team.
                    </p>
                </div>',
            "MEDIATOR" => '
                <div class="name">
                    MEDIATOR
                </div>
                <div class="sDesc">
                    When I am feeling fulfilled, I am a Connector and an Organiser.
                </div>
                <div class="fDesc">
                    <div>
                        <strong>The MEDIATOR:</strong>
                    </div>
                    <p><br><strong>' . $percent . '%</strong> of women who have taken the survey have the same PERSONA as you.<br><br><em>(Connector:Organiser)</em>  is a \'social creature\' who always seeks to get involved with activities with her peers and this contributes to her need to feel valued. As a good mediator she can empathise very well with both sides of an argument.
                    </p>
                    <p>
                    She seeks to support and comfort others, but she also needs to feel supported. She feels comforted when she wins over the confidence of others. She always seeks to understand the feedback others give her but without overly worrying about what others think about her.
                    </p>
                    <p>
                    With her experience she should encourage others to seek advice and support, and especially from those whom they trust the most and to reach out to women who have demonstrably succeeded.
                    </p>
                </div>',
            "BUSINESS EXECUTIVE" => '
                <div class="name">
                    EXECUTIVE / ACHIEVER
                </div>
                <div class="sDesc">
                    When I am feeling fulfilled, I am a Leader and a Connector.
                </div>
                <div class="fDesc">
                    <div>
                        <strong>The EXECUTIVE/ACHIEVER:</strong>
                    </div>
                    <p><br><strong>' . $percent . '%</strong> of women who have taken the survey have the same PERSONA as you.<br><br><em>(Leader: Connector)</em> has an abundance of what psychologists call fluid intelligence, this is the ability to carefully examine a problem and to apply tried and trusted (logical) techniques to reach a good solution.
                    </p>
                    <p>
                    She is a confident individual who has built up her success on a strategy of hard work and careful planning (making good decisions). That said, she is not afraid to make mistakes. As an overtly passionate person, she is able to take people with her and can be persuasive in a friendly way.
                    </p>
                    <p>
                    She can help others by letting them realise that luck doesn’t come by pure chance, but that with ambition and energy one can get lucky.
                    </p>
                </div>',
            "REFORMER" => '
                <div class="name">
                    REFORMER
                </div>
                <div class="sDesc">
                    When I am feeling fulfilled, I am a Leader and a Joy Seeker.
                </div>
                <div class="fDesc">
                    <div>
                        <strong>The REFORMER:</strong>
                    </div>
                    <p><br><strong>' . $percent . '%</strong> of women who have taken the survey have the same PERSONA as you.<br><br><em>(Leader:Joy Seeker)</em> is a charismatic individual, who is confident and strong. As an independent person she gets her strength from feeling, and being seen to be, successful and influential.
                    </p>
                    <p>
                    As someone who seeks the pleasures in life, she is not a person who worries about what others think, and despite the fact that she actively seeks to avoid pain, she is certainly not risk averse. She has had her ups and downs, and knows fully well that it’s OK to make mistakes.
                    </p>
                    <p>
                    She is happiest when she is championing a cause or solving a problem, the more adventurous the cause or problem the better. These are attitudes and skills that she could share with others by helping them to understand that when a door is shut they could just create their own door and so walk through it. Also, that failure is ok, use it to build up personal resilience.
                    </p>
                </div>',
            "INVESTIGATOR" => '
                <div class="name">
                    INVESTIGATOR
                </div>
                <div class="sDesc">
                    When I am feeling fulfilled, I am a Leader and an Organiser.
                </div>
                <div class="fDesc">
                    <div>
                        <strong>The INVESTIGATOR:</strong>
                    </div>
                    <p><br><strong>' . $percent . '%</strong> of women who have taken the survey have the same PERSONA as you.<br><br><em>(Leader:Organiser)</em> is a convergent thinker, someone who is able to make clear, logical deductions, given the facts. She is someone who is able to think critically and independently. She is likely to be a \'news junky\', someone who needs to know what is going on around her.
                    </p>
                    <p>
                    Other people may stereotype her as a \'workaholic\', but she is not just someone who puts in the hours, she is a leader and a problem solver - a very practical person who overcomes obstacles and tricky situations, the sort of character in a disaster movie who is going to lead the others out of danger.
                    </p>
                    <p>
                    People turn to her to get them out of trouble, because she does her homework when solving a problem, she finds out what the real obstacles are before proceeding. She can encourage others to stay curious, experience the new, and adopt the important lesson in life, which is that you should not regret later that you didn’t do your best.
                    </p>
                </div>',
            "ENTERTAINER" => '
                <div class="name">
                    ENTERTAINER
                </div>
                <div class="sDesc">
                    When I am feeling fulfilled, I am a Joy Seeker and a Connector.
                </div>
                <div class="fDesc">
                    <div>
                        <strong>The ENTERTAINER:</strong>
                    </div>
                    <p><br><strong>' . $percent . '%</strong> of women who have taken the survey have the same PERSONA as you.<br><br><em>(Joy Seeker:Connector)</em> is an extravert hedonist, meaning that she seeks pleasure and tries to avoid pain. The pursuit of happiness is equated with entertaining others as well as being entertained by others. Her enjoyment in the sensory pleasures in life are driven by the need to feel that life is fun and should be about laughing and making others laugh.
                    </p>
                    <p>
                    It\'s clear to all who know her that she loves social gatherings and wants to feel like she belongs. As a leader or manger she recognises that it is not about being a good boss – but encouraging her team to work towards a collective goal.
                    </p>
                    <p>
                    Like many experienced stand-up comedians, she knows that some of it will suck but some of it will be brilliant. Her excellent communication skills and ability to engage people is something she can share with others.
                    </p>
                </div>',
            "BON VIVANT" => '
                <div class="name">
                    BON VIVANT
                </div>
                <div class="sDesc">
                    When I am feeling fulfilled, I am a Joy Seeker and a Leader.
                </div>
                <div class="fDesc">
                    <div>
                        <strong>The BON VIVANT:</strong>
                    </div>
                    <p><br><strong>' . $percent . '%</strong> of women who have taken the survey have the same PERSONA as you.<br><br><em>(Joy Seeker:Leader)</em> craves pleasure and success. Indeed, she exudes confidence and strength as she overtly takes what she wants from life. But sometimes her carefree, relaxed, and calm external appearance can hide feelings of anxiety and worry.
                    </p>
                    <p>
                    She is often inspired by ideas that advocate social justice, and in fighting for a cause she can draw attention to it by daring to be highly visible and different. She would never sell herself short – she wouldn’t lie to hide something about herself, and she is certainly not afraid to call someone out if they are behaving in inappropriate ways.
                    </p>
                    <p>
                    Despite her apparent strength of character, as a role model she can tell others that life is never 100% wonderful there will always be bad days and that it is OK to feel vulnerable at times. One’s weaknesses can become one’s strengths.
                    </p>
                </div>',
            "CREATOR" => '
                <div class="name">
                    CREATOR
                </div>
                <div class="sDesc">
                    When I am feeling fulfilled, I am a Joy Seeker and an Organiser.
                </div>
                <div class="fDesc">
                    <div>
                        <strong>The CREATOR:</strong>
                    </div>
                    <p><br><strong>' . $percent . '%</strong> of women who have taken the survey have the same PERSONA as you.<br><br><em>(Joy Seeker:Organiser)</em> is someone with a lot of originality in the way they think. They are often enthusiasts who excel in a particular field or ability. They are likely to really enjoy their work, they are not someone purely driven by money. As is the case for most things she does in her life – it\'s clear that this girl just wants to have fun.
                    </p>
                    <p>
                    However, it is not reckless fun, she has the need to regulate how she feels. She is very organised, noticeably so as things in her home and office are seldom out of place. But there is an original flair in what she does. She probably loves DIY or re-designing her home or workplace.
                    </p>
                    <p>
                    The message she sends to other women is that in order to be able to have a job/career that you love, you need to take chances, listen to yourself, and be open to opportunities.
                    </p>
                </div>',
            "PEACEMAKER" => '
                <div class="name">
                    PEACEMAKER
                </div>
                <div class="sDesc">
                    When I am feeling fulfilled, I am an Organiser and a Connector.
                </div>
                <div class="fDesc">
                    <div>
                        <strong>The PEACEMAKER:</strong>
                    </div>
                    <p><br><strong>' . $percent . '%</strong> of women who have taken the survey have the same PERSONA as you.<br><br><em>(Organiser:Connector)</em> seeks both comfort control over her life. She is more introverted than extraverted and seeks an uncomplicated life. She like to comfort others and is a problem-solver and would make an excellent coach or counsellor.
                    </p>
                    <p>
                    She is very practical and highly organised in her life. She is most likely to be an active member of a club, society or WhatsApp group and is highly motivated to help others. As someone who is not afraid to ask for help, she makes the perfect confidant. She believes that you don\'t have to do it all alone and that it is better to take someone with you in life.
                    </p>
                    <p>
                    For her, creating a network can be the key to success. She can encourage others not to tell people how to feel, but to help them find it out for themselves.
                    </p>
                </div>',
            "CRISIS MANAGER" => '
                <div class="name">
                    CRISIS MANAGER
                </div>
                <div class="sDesc">
                    When I am feeling fulfilled, I am an Organiser and a Leader.
                </div>
                <div class="fDesc">
                    <div>
                        <strong>The CRISIS MANAGER:</strong>
                    </div>
                    <p><br><strong>' . $percent . '%</strong> of women who have taken the survey have the same PERSONA as you.<br><br><em>(Organiser:Leader)</em> is a very practical person who can survive very difficult situations, the sort of person who would easily survive if she found herself stranded in the jungle. She is often the first port of call when someone has a personal or even just a practical problem. She is cool, calm, and collected, and undeterred when others become aggressive or overly emotional or critical. Where others would run or shy away from difficulties, she faces it head on.
                    </p>
                    <p>
                    A lot of her strength actually comes from the admiration others display for her resilience and character. She can help others by emphasising that for most of us, success and personal strength does not come easily, it requires a huge effort in determination and hard work.
                    </p>
                </div>',
            "KNOWLEDGE SEEKER" => '
                <div class="name">
                    KNOWLEDGE SEEKER
                </div>
                <div class="sDesc">
                    When I am feeling fulfilled, I am an Organiser and a Joy Seeker.
                </div>
                <div class="fDesc">
                    <div>
                        <strong>The KNOWLEDGE SEEKER:</strong>
                    </div>
                    <p><br><strong>' . $percent . '%</strong> of women who have taken the survey have the same PERSONA as you.<br><br><em>(Organiser:Joy Seeker)</em> is a voracious reader and a logical thinker, like a scientist. She seeks joy from what she does. She sees life as a series of quests, individual fun-filled challenges that are somehow interlocked.
                    </p>
                    <p>
                    It\'s not just the practical sense of the here and now that dominates her thinking, it is in possibility and even fantasy – what might be unthinkable now, but may be possible in the future that brings her joy. The dream of a perfect world motivates her and gives her that ability to concentrate and stay focused on a problem or mission.
                    </p>
                    <p>
                    As a role model she can inspire others that to be successful one should be seen to act from an informed position and adhere to one\'s principles and not to do something just to seek social validation.
                    </p>
                </div>'
        ];

        $persona_development_descriptions = [
            "INFLUENCER" => '<p>She may, on occasion, have a tendency to feel alone or disconnected at work or home.  She may benefit from being more open to the signals of friends, family and colleagues and to practice her own style and voice, in order to communicate her ideas and influence others better.</p>',
            "EVERYONE'S FRIEND" => '<p>She may, at times, feel distant from her friends, family and colleagues and can lack confidence about her role in life and at work.  She may benefit from confidence building and learning how to find and work with mentors who can provide helpful advice and encouragement. Taking time out to relax is key.</p>',
            "MEDIATOR" => '<p>When busy, she can feel a bit out of control and not always able to plan and prioritise.  If she lacks the confidence to effectively communicate the challenges of this workload or set of deadlines, this can leave her feeling overwhelmed and less able to achieve her best.  If sustained without intervention, this can lead to a fall in confidence and self-belief.</p>',
            "BUSINESS EXECUTIVE" => '<p>She is likely to set herself big goals and expectations, but it may be time for her to invest in re-discovering what she really loves.  If she has lots going on, she may take her eye off the ball and find life or work have become monotonous or uninspiring.  She may need to reconnect and get a boost of encouragement and energy.</p>',
            "REFORMER" => '<p>She may, at times, underestimate her contribution and feel unmotivated.  She wants to make a difference but finds it difficult without positive encouragement. If she finds herself feeling low in energy or unappreciated, she may want to seek new inspiration and constructive feedback to help make work and life more rewarding and engaging.</p>',
            "INVESTIGATOR" => '<p>She may sometimes rely more on instinct and intuition than logic and facts.  Planning and organisation may not always come easily and sometimes she can find herself feeling a lack of control. She has a sense that life and work is \'happening to her\' rather than \'because of her\'. She may need to take stock and gain back control, to feel more engaged and inspired.</p>',
            "ENTERTAINER" => '<p>Although she can appear to others as \'together\', there are times when she may feel a little exposed and not as engaged or involved as she would like to be. This may lead her to be reluctant to try something new or ambitious where she might fail or be criticised, so she finds herself holding back.</p>',
            "BON VIVANT" => '<p>Although she can appear successful and in control to others, she may have moments where she lacks confidence and doubts her contribution.  It may be that her concern about putting herself forward, prevents her from experiencing the excitement and energy of trying or exploring bigger challenges and creative ideas.</p>',
            "CREATOR" => '<p>At her best, she may be full of energy, ideas, and passion, but she may not always be sure she\'s heading in the right direction.  She can find it challenging to relax and can even feel anxious. She may make action lists and plans, but she doesn\'t always follow through if complications and challenges arise.</p>',
            "PEACEMAKER" => '<p>She is likely to be hard working and conscientious but may not always feel assured of her role or the direction she\'s heading. Sometimes she wishes she could feel more in control. She can, at times, feel undervalued and find herself feeling disconnected. Reaching out to people will help her feel more engaged and realise she\'s not alone - everyone needs a little help sometimes.</p>',
            "CRISIS MANAGER" => '<p>To others, she might appear unfazed and in control of her life, but she may judge herself a little more harshly. When she faces challenges or crisis, she may feel stress acutely and find it difficult to present herself as calm and in control. She sometimes needs to remind herself that she has lots to offer and to trust in her strengths to boost her confidence.</p>',
            "KNOWLEDGE SEEKER" => '<p>She may approach work and life as a series of challenges. She isn\'t always keen to plan and may not feel in control. If she\'s feeling worried or anxious, she may put others under pressure without meaning to. She might feel more relaxed and her challenges easier to overcome, if she were to direct her positive energy and inspiration toward planning and keeping things simple.</p>'
        ];


        $this->persona_id = $persona_id[$persona];

        $lowest_scores = collect($base_scores)->sort()->keys();
        $res2 = [$lowest_scores[0], $lowest_scores[1]];
        $persona_lowest = collect($persona_combination)->filter(function ($v) use ($res2) {
            return $v == $res2;
        })->keys()[0];

        if (false === Counter::whereParticipantId($user->username)->exists()) {
            Counter::create([
                'participant_id' => $user->username,
                'persona' => [
                    'top1' => $score[0],
                    'top2' => $score[1],
                    'lowest1' => $lowest_scores[0],
                    'lowest2' => $lowest_scores[1],
                    'result' => $persona,
                    'result_lowest' => $persona_lowest
                ],
                'source' => 'tracker',
                'meta' => [
                    'age_group' => $user->config['screener']['age'],
                    'country' => $user->config['screener']['country'],
                    'gender' => $user->config['screener']['gender']
                ]
            ]);
        }

        //return "Based on your results, we think you are {$phase4_char[$score[0]]}, but you also tend to be {$phase4_char[$score[1]]}. See descriptions below. <br><br><br>{$phase4_desc[$score[0]]} <br><br>{$phase4_desc[$score[1]]}";
        //return "Your emotional motivators indicate you are {$persona_char[$persona]}.<br><br>{$persona_descriptions[$persona]} It takes a lot of women to shape the world, {$percent}% of women who have taken the survey have the same persona as you.";
        // return "{$persona_descriptions[$persona]} It takes a lot of women to shape the world, <strong>{$percent}%</strong> of women who have taken the survey have the same persona as you.<br>{$persona_development_descriptions[$persona_lowest]}<p><em>If any of this resonates with you and you’re interested in self-development, discover more by clicking the Free Content and Coaching buttons at the bottom of this page.</em></p>";
        return "{$persona_descriptions[$persona]} {$persona_development_descriptions[$persona_lowest]}<p><em>If any of this resonates with you and you’re interested in self-development, discover more by clicking the Free Content and Coaching buttons at the bottom of this page.</em></p>";
    }
}
