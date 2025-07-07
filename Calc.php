<?php
namespace app\common\model;

class Calc
{
    protected static $operate = [
        '(',
        ')',
        '+',
        '-',
        '*',
        '/',

        '*+',
        '*-',
        '/+',
        '/-',
    ];

    protected static $twoElementOperation = [
        '+',
        '-',
        '*',
        '/',  

        '*+',
        '*-',
        '/+',
        '/-',
    ];

    public static function exec($filter,  $data='')
    {
        $infix   = self::stringToOperateArray($filter);
        $postfix = self::infixToPostfix($infix);
        $data  = is_array($data) ? $data : [];
        $value = self::calculation($postfix, $data);

        return $value;
    }

    public static function ceil($num)
    {
        $ret     = number_format($num,2,'.','');
        return $ret<$num?bcadd($ret,0.01,2):$ret;
    }

    public static function calc($filter,  $scale=2,  $data='')
    {
        $value   = floatval(self::exec($filter,''));
        $ret     = floor($value*100)/100;
        return $ret<$value?bcadd($ret,0.01,2):$ret;
    }

    //四舍五入
    public static function round($filter,  $scale=2,  $data='')
    {
        $value = floatval(self::exec($filter,''));
        return round($value, $scale);
    }

    //抹零
    public static function roundZero($filter,  $scale=2,  $data='')
    {
        $value = floatval(self::exec($filter,''));
        return floor($value*100)/100;
    }

    /**
     * 将中缀表达式转换为后缀表达式
     * @param array $infixExpression
     */
    public static function infixToPostfix($infixExpression)
    {
        $stacks = [];
        $output = [];
        array_walk($infixExpression, function ($value) use (&$stacks, &$output) {
            if (!self::isOperate($value)) {
                $output[] = $value;
            } else {
                if (empty($stacks) || $value == '(' || self::compareOperate($value, end($stacks)) == 1) {
                    $stacks[] = $value;
                } else if ($value == ')') {
                    while (!empty($stacks) && ($last = array_pop($stacks)) != '(') {
                        $output[] = $last;
                    }
                } else if (self::compareOperate($value, end($stacks)) <= 0) {
                    while (!empty($stacks) && end($stacks) != '(') {
                        if (self::compareOperate(end($stacks), $value) > -1) {
                            $output[] = array_pop($stacks);
                        } else {
                            break;
                        }
                    }
                    $stacks[] = $value;
                }
            }
        });
        while (!empty($stacks)) {
            $end = array_pop($stacks);
            if ($end != '(' && $end != ')') {
                $output[] = $end;
            }
        }
        return $output;
    }


    public static function calculation($postfix, $data)
    {
        $stacks = [];        
        array_walk($postfix, function ($value) use ($data, &$stacks) {
            if (self::isOperate($value)) {
               if (count($stacks) >= 2) {
                    $two      = array_pop($stacks);
                    $one      = array_pop($stacks);
                    $stacks[] = self::twoElementOperateion($one, $two, $value);
                }
            } else {
                if (substr($value, 0, 1) == '{' && substr($value, -1, 1) == '}') {
                    $value    = substr($value, 1, -1);
                    $stacks[] = array_key_exists($value, $data) ? $data[$value] : 0;
                } else {
                    $stacks[] = $value;
                }
            }
        });
        return count($stacks) > 0 ? array_pop($stacks) : 0;
    }

    /**
     * 将字符串转换为中缀表达式
     * 采用过滤敏感词的算法
     * @param string $string
     */
    public static function stringToOperateArray($string)
    {
        $string = trim($string);
        $string = html_entity_decode($string);
        $len    = strlen($string);
        $return = [];

        $operateIndex = [];
        foreach (self::$operate as $value) {
            !isset($operateIndex[$value[0]]) && $operateIndex[$value[0]] = [];
            $operateIndex[$value[0]][] = $value;
        }

        $left = 0;
        $i    = 0;
        while ($i < $len) {
            if ($string[$i] == ' ') {
                if ($i > $left) {
                    $return[] = substr($string, $left, $i - $left);
                }
                $left = $i + 1;
            } else if (!empty($operateIndex[$string[$i]])) {
                $offset   = 0;
                $lenValue = 0;
                foreach ($operateIndex[$string[$i]] as $value) {
                    $lenValue = strlen($value);
                    if (substr($string, $i, $lenValue) == $value) {
                        $offset = $lenValue > $offset ? $lenValue : $offset;
                    }
                }
                if ($offset > 0 && ($string[$i] != '' || ($left != $i))) {
                    $i > $left && $return[] = substr($string, $left, $i - $left);
                    $return[] = substr($string, $i, $offset);
                    $i        = $i + $offset - 1;
                    $left     = $i + 1;
                }              
        }

            $i++;
        }
        if ($len > $left) {
            $return[] = substr($string, $left, $len - $left);
        }

        return $return;
    }

    protected static function isOperate($string)
    {
        if (in_array($string, self::$operate)) {
            return true;
        }
        return false;
    }

    protected static function compareOperate($s1, $s2)
    {
        $weight = [
            ['*', '/',  '*+','*-','/+','/-',],
            ['+', '-', '.'],
        ];

        foreach ($weight as $value) {
            if (in_array($s1, $value)) {
                if (in_array($s2, $value)) {
                    return 0;
                } else {
                    return 1;
                }
            } else if (in_array($s2, $value)) {
                return -1;
            }
        }
        return 0;
    }

    protected static function twoElementOperateion($a, $b, $operate)
    {
        switch ($operate) {
            case '+' :
                return bcadd($a,$b,15);
                break;
            case '-' :
                return bcsub($a,$b,15);
                break;
            case '*' :
            case '*+' :
                return bcmul($a,$b,15);
                break;
            case '*-' :
                return 0-bcmul($a,$b,15);
                break;
            case '/' :
            case '/+' :
                return bcdiv($a,$b,15);
                break;
            case '/-' :
                return 0-bcdiv($a,$b,15);
                break;
            default :
                return 0;
        }

    }
}
//var_dump(calc("20.540000/(13.500000+5.000000)-1"));
//var_dump(calc::exec("(1+2)*6",""));
//var_dump(calc::exec("844.00-697.99-146.00",""));
//var_dump(calc::exec("100.00-99.90",""));

//var_dump(844.00-697.99-146.00);
//var_dump(100.00-99.90);


//var_dump(calc::calc("100-99.9",18));
//var_dump(100-99.9);

//exit;

