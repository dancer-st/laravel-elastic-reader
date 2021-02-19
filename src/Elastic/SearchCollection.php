<?php


namespace Merkeleon\ElasticReader\Elastic;

use Illuminate\Support\Collection;
use Illuminate\Support\Arr;

class SearchCollection extends Collection
{
    protected $took;
    protected $isTimedOut;
    protected $total;
    protected $maxScore;
    protected $shards;
    protected $lastSort;


    public static function createFromElasticResponse($elasticResponse = [], callable $callbackHit = null, array $callbacks = null)
    {
        if (!$callbackHit)
        {
            $items = $elasticResponse['hits']['hits'];
        }
        else
        {
            $items = array_map(
                function ($item) use ($callbackHit) {
                    return $callbackHit($item);
                },
                $elasticResponse['hits']['hits']
            );
        }

        $items = (new static($items))
            ->setTook($elasticResponse['took'])
            ->setTimedOut($elasticResponse['timed_out'])
            ->setTotal($elasticResponse['hits']['total'])
            ->setMaxScore($elasticResponse['hits']['max_score'])
            ->setShards($elasticResponse['_shards'])
            ->setLastSort(static::getLastSortFromHits($elasticResponse['hits']['hits']));

        if ($callbacks)
        {
            foreach ($callbacks as $callback)
            {
                if (is_callable($callback))
                {
                    $callback($items);
                }
            }
        }

        return $items;
    }

    public function setTook(int $took)
    {
        $this->took = $took;

        return $this;
    }

    /**
     * @return int
     */
    public function getTook()
    {
        return $this->took;
    }

    public function setTimedOut(bool $timeOut)
    {
        $this->isTimedOut = $timeOut;

        return $this;
    }


    /**
     * @return bool
     */
    public function isTimedOut()
    {
        return $this->isTimedOut;
    }


    public function setTotal($total)
    {
        $this->total = $total['value'];

        return $this;
    }

    /**
     * @return int
     */
    public function getTotal()
    {
        return $this->total;
    }


    public function setMaxScore(int $maxScore = null)
    {
        $this->maxScore = $maxScore;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxScore()
    {
        return $this->maxScore;
    }

    public function setShards(array $shards)
    {
        $this->shards = $shards;

        return $this;
    }

    public function getShards()
    {
        return $this->shards;
    }

    protected static function getLastSortFromHits($hits)
    {
        if (empty($hits))
        {
            return null;
        }
        $lastHit = last($hits);

        return Arr::get($lastHit, 'sort');
    }

    public function setLastSort($lastSort)
    {
        $this->lastSort = $lastSort;

        return $this;
    }

    public function getLastSort()
    {
        return $this->lastSort;
    }
}
