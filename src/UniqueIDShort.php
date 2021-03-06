<?php
namespace Ltxiong\AlgTool;

/**
 * @desc 基于雪花算法进行改造的全局唯一ID变种算法(支持JavaScript支持的最大整型的53位)
 * 可以参考 https://www.liaoxuefeng.com/article/1280526512029729，将机器位和单位时间粒度调大一些
 * @example
 * 
 * use Ltxiong\AlgTool\UniqueIDShort;
 * $work = new UniqueIDShort(2, 30);
 * for($i = 0; $i < 100; $i++) {
 *     $id = $work->getNextUniqueID();
 *     echo $id . "    " . PHP_EOL;
 * }
 * 
 */

class UniqueIDShort
{
    /**
     * 总计53位，其中，32位的秒数，5位机器占位符，2位IDC数据中心标识，14位秒内自增数
     * 此值越大, 生成的ID越小
     * 固定一个小于当前时间的毫秒数 2020/12/01 00:00:00
     */
    const T_WEPOCH =  1606752000;

    /**
     * 机器标识占的位数  同一个数据中心(IDC)最多可部32台
     */
    const WORKER_ID_BITS = 5;

    /**
     * 数据中心标识占的位数(一般的公司IDC数量不会太多，绝大部分公司IDC也就1~2个，
     * 如超过4个，可再适当增加相应IDC位数) 最多可有4个IDC
     */
    const IDC_ID_BITS = 2;
 
    /**
     * 秒内自增数点的位数，同一秒内最多可产生 16384个不重复的号
     */
    const SEQUENCE_BITS = 14;

    /**
     * 机器标识id，取值范围0~31
     *
     * @var integer
     */
    protected $work_id = 0;

    /**
     * 数据中心(IDC)标识id，取值范围0~3
     * 
     * @var integer
     */
    protected $idc_id = 0;

    /**
     * 上一次获取数据的秒数
     */
    static $last_timestamp = -1;

    /**
     * 当前秒内计数间隔
     */
    static $sequence = 0;

    /**
     * 同一秒内最多可产生的数量最大ID
     */
    static $sequence_mask = 0;

    /**
     * 时间毫秒要左移的位数
     */
    static $timestamp_left_shift = 0;

    /**
     * 数据中心ID要左移的位数
     */
    static $idc_id_shift = 0;

    /**
     * 机器ID要左移的位数
     */
    static $worker_id_shift = 0;

    /**
     * 析构函数，初始化时调用
     *
     * @param int $idc_id  数据中心(IDC)标识id
     * @param int $work_id  机器标识id
    */
    public function __construct($idc_id, $work_id)
    {
        //机器ID范围判断与有效范围校验
        $max_worker_id = -1 ^ (-1 << self::WORKER_ID_BITS);
        if($work_id > $max_worker_id || $work_id< 0)
        {
            throw new Exception("workerId can't be greater than " . $max_worker_id . " or less than 0");
        }
        //数据中心ID范围判断与有效范围校验
        $max_idc_id = -1 ^ (-1 << self::IDC_ID_BITS);
        if ($idc_id > $max_idc_id || $idc_id < 0) 
        {
            throw new Exception("idc Id can't be greater than " . $max_idc_id . " or less than 0");
        }
        
        //  机器标识id初始化
        $this->work_id = $work_id;

        //  数据中心(IDC)标识id初始化
        $this->idc_id = $idc_id;

        // 同一毫秒内最多可产生的数量最大ID
        self::$sequence_mask = -1 ^ (-1 << self::SEQUENCE_BITS);

        //时间毫秒要左移的位数
        self::$timestamp_left_shift = self::SEQUENCE_BITS + self::WORKER_ID_BITS + self::IDC_ID_BITS;

        //数据中心ID要左移的位数
        self::$idc_id_shift = self::SEQUENCE_BITS + self::WORKER_ID_BITS;

        //机器ID要左移的位数
        self::$worker_id_shift = self::SEQUENCE_BITS;
    }

    /**
     * 根据当前时间戳(秒) 生成全局唯一ID
     *
     * @return int $nextId
    */
    public function getNextUniqueID()
    {
        //取当前时间秒
        $timestamp = $this->timeGen();
        $last_timestamp = self::$last_timestamp;
        //判断时钟是否正常
        if ($timestamp < $last_timestamp) 
        {
            throw new Exception("Clock moved backwards.  Refusing to generate id for %d milliseconds", ($last_timestamp - $timestamp));
        }
        //生成唯一序列
        if ($last_timestamp == $timestamp) 
        {
            self::$sequence = (self::$sequence + 1) & self::$sequence_mask;
            if (self::$sequence == 0) 
            {
                $timestamp = $this->tilNextSec($last_timestamp);
            }
        } 
        else 
        {
            self::$sequence = 0;
        }
        self::$last_timestamp = $timestamp;
        //组合4段数据返回: 时间戳.数据标识.工作机器.序列
        $nextId = (($timestamp - self::T_WEPOCH) << self::$timestamp_left_shift) | ($this->idc_id << self::$idc_id_shift) | ($this->work_id << self::$worker_id_shift) | self::$sequence;
        return $nextId;
    }
    
    /**
     * 取当前时间秒
     *
     * @return float $timestramp
    */
    protected function timeGen()
    {
        // $timestramp = (float)sprintf("%.0f", microtime(true) * 1000);
        $timestramp = time();
        return  $timestramp;
    }

    /**
     * 取下一秒
     *
     * @return float $timestramp
    */
    protected function tilNextSec($last_timestamp)
    {
        $timestamp = $this->timeGen();
        while ($timestamp <= $last_timestamp) 
        {
            $timestamp = $this->timeGen();
        }
        return $timestamp;
    }
    
}
