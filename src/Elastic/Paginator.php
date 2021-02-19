<?php


namespace Merkeleon\ElasticReader\Elastic;

use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator as LaravelPaginator;

class Paginator
{
    const PREVIOUS_PAGE = 'previous_page';
    const LAST_SEARCHED = 'last_searched';
    const SIZE_LIMIT    = 10000;

    private $searchModel;
    private $currentPage  = 1;
    private $perPage      = 25;
    private $total        = 0;
    private $previousPage = null;
    private $lastSearched = null;

    public function paginate(SearchModelNew $searchModel, int $perPage)
    {
        $this->init($searchModel, $perPage);

        $this->searchModel->query()
                          ->searchAfter($this->getSearchAfter())
                          ->size($this->getSize());

        $results = $this->searchModel->get();

        $this->updateRequest($results->getLastSort());

        $rows = $results->forPage($this->getFromPage(), $this->perPage);

        return $this->paginator($rows, $results->getTotal(), $this->perPage, $this->currentPage, [
            'path'     => LaravelPaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    public function getEmtyPaginator()
    {
        return $this->paginator([], $this->total, $this->perPage, $this->currentPage, [
            'path'     => LaravelPaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    private function init(SearchModelNew $searchModel, int $perPage)
    {
        $this->searchModel  = $searchModel;
        $this->currentPage  = LaravelPaginator::resolveCurrentPage();
        $this->perPage      = $perPage;
        $this->total        = $searchModel->getTotal();
        $this->previousPage = $this->getPreviousPage();
        $this->lastSearched = $this->getLastSearched();

    }

    private function getSearchAfter()
    {
        if ($this->isFirstPage())
        {
            return null;
        }

        if ($this->paginateBack())
        {
            return $this->findSearchAfterByPaginatingBack();
        }

        return $this->lastSearched;
    }

    private function getSize()
    {
        if ($this->isFirstPage() || $this->paginateBack())
        {
            $size = $this->perPage;
        }
        else
        {
            $size = ($this->currentPage - $this->previousPage) * $this->perPage;
        }

        if ($size > self::SIZE_LIMIT)
        {
            throw new PaginatorException("Size limit exceeded");
        }

        return $size;
    }

    private function getFromPage()
    {
        if ($this->isFirstPage() || $this->paginateBack())
        {
            return 1;
        }

        return $this->currentPage - $this->previousPage;
    }

    private function updateRequest($lastSort)
    {
        $request = request()->request;
        $request->set(self::PREVIOUS_PAGE, $this->currentPage);
        $request->set(self::LAST_SEARCHED, $lastSort);
    }

    private function paginateBack()
    {
        return $this->isLastPage() || $this->previousPage >= $this->currentPage;
    }


    private function getLastPage()
    {
        return max((int)ceil($this->total / $this->perPage), 1);
    }

    private function isLastPage()
    {
        return $this->currentPage == $this->getLastPage();
    }

    private function isPreviousPageIsLastPage()
    {
        return $this->previousPage == $this->getLastPage();
    }

    private function getItemsCountInPreviousPage()
    {
        if ($this->isPreviousPageIsLastPage())
        {
            return $this->getItemsCountInLastPage();
        }

        return $this->perPage;
    }

    private function isFirstPage()
    {
        return $this->currentPage == 1;
    }

    private function getItemsCountInLastPage()
    {
        $itemsCountWithoutLastPage = ($this->getLastPage() - 1) * $this->perPage;

        return $this->total - $itemsCountWithoutLastPage;
    }

    private function findSearchAfterByPaginatingBack()
    {
        $pageDiff    = $this->previousPage - $this->currentPage;
        $searchAfter = $this->isLastPage() ? null : $this->lastSearched;

        if ($this->isLastPage())
        {
            $size = $this->getItemsCountInLastPage() + 1;
        }
        else
        {
            $size = $pageDiff * $this->perPage + $this->getItemsCountInPreviousPage();
        }

        if ($size > self::SIZE_LIMIT)
        {
            throw new PaginatorException("Size limit exceeded");
        }

        $searchModel = clone $this->searchModel;

        $searchModel
            ->query()
            ->reversSort()
            ->searchAfter($searchAfter)
            ->size($size);

        $results = $searchModel->get();

        return $results->getLastSort();
    }

    public function paginator($items, $total, $perPage, $currentPage, $options)
    {
        return Container::getInstance()
                        ->makeWith(LengthAwarePaginator::class, compact(
                            'items', 'total', 'perPage', 'currentPage', 'options'
                        ));
    }

    private function getPreviousPage()
    {
        return request()->get(self::PREVIOUS_PAGE);
    }

    private function getLastSearched()
    {
        $lastSearched = request()->get(self::LAST_SEARCHED);

        if (empty($lastSearched))
        {
            return $lastSearched;
        }

        return array_map(
            function ($val) {
                return is_numeric($val) ? $val + 0 : $val;
            },
            $lastSearched
        );
    }
}
