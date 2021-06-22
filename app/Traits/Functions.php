<?php

namespace App\Traits;

use App\Models\Counter;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait Functions
{
    protected $count = 0;

    public function overAllPhase2()
    {
        $cache_key = 'overAllPhase2';
        //$this->count = User::whereNotNull('data')->count();
        if (Cache::has($cache_key)) {
            //$count = User::whereNotNull('data')->count();
            $count = Counter::has('user')->count();
            $cache_count = (int) Cache::get($cache_key)['count'];
            if ($count === $cache_count) {
                $results = Cache::get($cache_key)['results'];
                $this->count = Cache::get($cache_key)['count'];
                //$this->count = $count;
            } else {
                $count = Counter::has('user')->count();
                $this->count = $count;
                $users = User::whereNotNull('data')->get();
                $results = $this->processOverallPhase2($users);
                Cache::put($cache_key, [
                    'count' => $count,
                    'results' => $results
                ]);
            }
        } else {
            $count = Counter::has('user')->count();
            $users = User::whereNotNull('data')->get();
            $results = $this->processOverallPhase2($users);
            $this->count = $count;
            Cache::put($cache_key, [
                'count' => $count,
                'results' => $results
            ]);
        }
        return $results;

    }

    private function processOverallPhase2($users)
    {
        //$this->count = $users->count();
        $data = collect($users)->pluck('scores');
        $collections = $data->map(function ($v) {
            return $v['link1']['phase2'];
        });
        $indexes = [
            'WORK',
            'SELF',
            'SOCIETY',
            'MONEY',
            'RELATIONSHIPS'
        ];
        $results = [];
        foreach (generator($indexes) as $index) {
            $tmp_r = [];
            $tmp_c = [];
            foreach (generator($collections) as $collection) {
                $tmp_r[] = $collection['relative'][$index];
                $tmp_c[] = $collection['current'][$index];
            }
            $results['relative'][$index] = round(collect($tmp_r)->avg(), 0);
            $results['current'][$index] = round(collect($tmp_c)->avg(), 0);
        }
        return $results;
    }

    public function phase2($username = null)
    {
        if (is_null($username)) {
            if (Cache::has('phase2')) {
                $count = User::whereNotNull('data')->count();
                $cache_count = Cache::get('phase2')['count'];
                if ($count === $cache_count) {
                    $users = Cache::get('phase2')['data'];
                } else {
                    $users = User::whereNotNull('data')->get();
                    Cache::put('phase2', [
                        'count' => $users->count(),
                        'data' => $users
                    ]);
                }
            } else {
                $users =  User::whereNotNull('data')->get();
                Cache::put('phase2', [
                    'count' => $users->count(),
                    'data' => $users
                ]);
            }
        } else {
            $users = User::whereUsername($username)->whereNotNull('data')->get();
        }
        $results = [];
        $relatives = [];
        $currents = [];
        $this->count = $users->count();
        if ($this->count == 0) {
            return [
                'current' => [
                    'WORK' => null,
                    'MONEY' => null,
                    'SOCIETY' => null,
                    'SELF' => null,
                    'RELATIONSHIPS' => null
                ],
                'relative' => [
                    'WORK' => null,
                    'MONEY' => null,
                    'SOCIETY' => null,
                    'SELF' => null,
                    'RELATIONSHIPS' => null
                ]
            ];
        }
        foreach (generator($users) as $user) {
            if (!isset($user->data['link1'])) {
                continue;
            }
            $d1 = $user->data['link1']['XaDe2uJ5rO']['data'];
            $d2 = $user->data['link1']['UskiAg0F2S']['data'];
            $tmp = [];
            foreach (generator($d1) as $key => $choice) {
                $tmp[$choice['choice']['target_name']][] = $choice['choice']['target_value'];
            }
            foreach (generator($tmp) as $target => $choices) {
                $results['relative'][$user->id][$this->phase2_text($target)] = round(100 * (collect($choices)->count() / 4), 0);
            }
            $tmp = [];
            foreach (generator($d2) as $key => $choice) {
                $tmp[$choice['choice']['target_name']][] = $choice['choice']['target_value'];
            }
            foreach (generator($tmp) as $target => $choices) {
                $results['current'][$user->id][$this->phase2_text($target)] = round(100 * (collect($choices)->count() / 4), 0);
            }
        }

        $texts = ['WORK', 'MONEY', 'SOCIETY', 'SELF', 'RELATIONSHIPS'];
        foreach ($results['relative'] as $scores) {
            foreach (generator($texts) as $text) {
                $relatives[$text][] = $scores[$text] ?? null;
            }
        }
        foreach (generator($results['current']) as $scores) {
            foreach (generator($texts) as $text) {
                $currents[$text][] = $scores[$text] ?? null;
            }
        }
        return [
            'relative' => collect($relatives)->map(function ($v) {
                return round(collect($v)->avg(), 0);
            })->toArray(),
            'current' => collect($currents)->map(function ($v) {
                return round(collect($v)->avg(), 0);
            })->toArray()
        ];
    }

    private function phase2_text($text)
    {
        return Str::of($text)->afterLast(' ')->upper()->__toString();
    }

    public function phase4($username = null)
    {
        $users = is_null($username) ? User::all() : User::whereUsername($username)->get();
        $group = [
            'THE CONNECTOR' => ['protected', 'secure', 'comforted', 'supported', 'like I belong', 'engaged', 'involved', 'valued'],
            'THE ALPHA FEMALE' => ['confident', 'strong', 'stimulated', 'energised', 'independent', 'excited', 'successful', 'free', 'efficient', 'creative', 'inspired'],
            'THE PLEASURE SEEKER' => ['like I am having fun', 'relaxed', 'wonderful', 'a sense of enjoyment', 'happy', 'calm'],
            'THE ORGANISER' => ['organised', 'practical', 'in control', 'sensible']
        ];
        $results = [];
        foreach (generator($users) as $user) {
            $data = $user->data['link1']['nfiuDOm5L7']['data'];
            $tmp = [];
            foreach (generator($group) as $arch => $attributes) {
                foreach (generator($attributes) as $attribute) {
                    $tmp[$arch][$attribute] = collect($data)->filter(function ($v) use ($attribute) {
                        return strip_tags($v['prime_name']) == $attribute && $v['choice']['target_value'] == '1';
                    })->isNotEmpty() ? 1 : null;
                }
            }
            $results[$user->id] = collect($tmp)->map(function ($v) {
                $v = collect($v);
                return round(($v->sum() / $v->count()) * 100, 2);
            })->sortDesc()->toArray();
        }
        $tmp = [];
        foreach (generator($results) as $userid => $values) {
            foreach (generator($values) as $arch => $value) {
                $tmp[$arch][] = $value;
            }
        }
        $res = [];
        foreach (generator($tmp) as $arch => $values) {
            $res[$arch] = round(collect($values)->avg(), 0);
        }
        return $res;
    }

    public function phaseA($username = null, $group = 1)
    {
        $codes = [
            1 => ['P7zUYo', 'O1N2YQ'],
            2 => ['0BKunP', 'iJ4mJe'],
            3 => ['tviVrk', 'rVlhIj'],
            4 => ['uKNsGc', 'ao9r95'],
            5 => ['sLy4cx', 'lVc9EW']
        ];
        $users = is_null($username) ? User::all() : User::whereUsername($username)->get();
        $sets = [];
        foreach (generator($users) as $user) {
            foreach ([1, 2] as $i) {
                $data = $user->data['link' . ($group + 1)][$codes[$group][$i - 1]]['data'];
                foreach (generator($data) as $rt) {
                    if ($rt['choice']['target_value'] == '2') {
                        $sets[$i][strip_tags($rt['prime_name'])][] = 50;
                    } else if ($rt['choice']['target_value'] == '3') {
                        $sets[$i][strip_tags($rt['prime_name'])][] = 0;
                    }
                }
                $rts = collect($data)->filter(function ($v) {
                    return $v['choice']['target_value'] == '1';
                })->sortBy('rt')->values();
                if ($rts->count() > 0) {
                    $step = round(50 / $rts->count(), 2);
                    foreach (generator($rts) as $key => $val) {
                        $sets[$i][strip_tags($val['prime_name'])][] = round(100 - ($step * $key), 0);
                    }
                }
            }
        }
        $results = [];
        foreach (generator($sets) as $set => $res) {
            foreach (generator($res) as $key => $values) {
                //$key = strip_tags(Str::of($key)->lower()->__toString());
                $results['set' . $set][$key] = round(collect($values)->avg(), 0);
            }
        }
        foreach ($results['set2'] as $key => $score) {
            $set1 = $results['set1'][$key];
            $results['set2'][$key] = $score > $set1 ? $set1 : $score;
        }
        return $results;
    }

    public function phaseB($username = null, $group = 1)
    {
        $code = [
            1 => ['uxK0dKPvqG', 'nfiuDOm5L7']
        ];
        $dimensions = [
            'safety',
            'connectedness',
            'enjoyment',
            'excitement',
            'power',
            'in control'
        ];
        $users = is_null($username) ? User::all() : User::whereUsername($username)->get();
        $tmp = [];
        foreach (generator($users) as $index => $user) {
            $data1 = $user->data['link' . ($group)][$code[$group][0]]['data'];
            $data2 = $user->data['link' . ($group)][$code[$group][1]]['data'];
            foreach (generator($dimensions) as $key) {
                foreach ([1, 2] as $i) {
                    $d = collect(${'data' . $i})->filter(function ($v) use ($key) {
                        return $key === trim(strtolower($v['dim_name']));
                    });
                    $t1 = $d->filter(function ($v) {
                        return $v['choice']['target_value'] == '1';
                    })->count();
                    $t2 = $d->filter(function ($v) {
                        return $v['choice']['target_value'] == '2';
                    })->count();
                    $t3 = $d->filter(function ($v) {
                        return $v['choice']['target_value'] == '3';
                    })->count();
                    $percent = round(((($t1 * 1) + ($t2 * 0.25) + ($t3 * 0)) / 5) * 100, 0);
                    //dump($t1 . ' ' . $t2 . ' ' . $t3 . ' - ' . $percent);
                    $tmp['set' . $i][$index][$key] = $percent;
                }
            }
        }
        $results = [];

        $tmp2 = [];
        $tmp3 = [];
        foreach (generator($tmp['set1']) as $d) {
            foreach (generator($d) as $dim => $dd) {
                $tmp2[$dim][] = $dd;
            }
        }
        foreach (generator($tmp['set2']) as $d) {
            foreach (generator($d) as $dim => $dd) {
                $tmp3[$dim][] = $dd;
            }
        }
        foreach (generator($tmp2) as $dim => $scores) {
            $results['set1'][strtoupper($dim)] = round(collect($scores)->avg(), 0);
        }
        foreach (generator($tmp3) as $dim => $scores) {
            $score = round(collect($scores)->avg(), 0);
            $results['set2'][strtoupper($dim)] = $score > $results['set1'][strtoupper($dim)] ? $results['set1'][strtoupper($dim)] : $score;
        }
        return $results;
    }
}
