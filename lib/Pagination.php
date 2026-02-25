<?php
namespace Lib;

/**
 * Pagination — 独立分页器
 *
 * 用法：
 *   $pager = new Pagination($totalRows, $currentPage, $perPage);
 *   $pager->offset();      // SQL OFFSET 值
 *   $pager->totalPages();  // 总页数
 *   $pager->links('/posts?page='); // 生成 HTML 分页链接
 *
 *   // 或直接从查询构建
 *   $pager = Pagination::fromQuery($this->db->table('posts')->where('status=?',['published']), $page, 10);
 *   $posts = $pager->items();      // 当前页数据
 */
class Pagination
{
    private int $total;
    private int $page;
    private int $perPage;
    private int $totalPages;
    private array $items = [];

    public function __construct(int $total, int $page = 1, int $perPage = 10)
    {
        $this->total      = max(0, $total);
        $this->perPage    = max(1, $perPage);
        $this->totalPages = max(1, (int)ceil($this->total / $this->perPage));
        $this->page       = max(1, min($page, $this->totalPages));
    }

    /**
     * 从 DB 查询自动构建分页
     *
     * @param DB  $query   查询构建器
     * @param int $page    当前页码
     * @param int $perPage 每页条数
     * @param int $count   预设总数（传入后跳过 SELECT COUNT，省一次查询）
     */
    public static function fromQuery(DB $query, int $page = 1, int $perPage = 10, int $count = -1): self
    {
        $total = $count >= 0 ? $count : $query->count();
        $pager = new self($total, $page, $perPage);
        $pager->items = $query
            ->limit($perPage, $pager->offset())
            ->fetchAll();
        return $pager;
    }

    /** 当前页码 */
    public function currentPage(): int  { return $this->page; }

    /** 每页条数 */
    public function perPage(): int      { return $this->perPage; }

    /** 总记录数 */
    public function total(): int        { return $this->total; }

    /** 总页数 */
    public function totalPages(): int   { return $this->totalPages; }

    /** SQL OFFSET */
    public function offset(): int       { return ($this->page - 1) * $this->perPage; }

    /** 是否有上一页 */
    public function hasPrev(): bool     { return $this->page > 1; }

    /** 是否有下一页 */
    public function hasNext(): bool     { return $this->page < $this->totalPages; }

    /** 当前页数据（需通过 fromQuery 或 setItems 设置） */
    public function items(): array      { return $this->items; }

    /** 设置当前页数据 */
    public function setItems(array $items): self { $this->items = $items; return $this; }

    /**
     * 生成 HTML 分页链接
     *
     * @param string $urlPattern URL 模式，{page} 会被替换为页码
     *                           例如：'/posts?page={page}' 或 '/posts/page/{page}'
     */
    public function links(string $urlPattern = '?page={page}'): string
    {
        if ($this->totalPages <= 1) return '';

        $html = '<nav class="pagination">';

        // 上一页
        if ($this->hasPrev()) {
            $url = str_replace('{page}', $this->page - 1, $urlPattern);
            $html .= "<a href=\"{$url}\" class=\"page-prev\">&laquo; 上一页</a>";
        }

        // 页码（显示当前页前后各 2 页）
        $start = max(1, $this->page - 2);
        $end   = min($this->totalPages, $this->page + 2);

        if ($start > 1) {
            $url = str_replace('{page}', '1', $urlPattern);
            $html .= "<a href=\"{$url}\" class=\"page-num\">1</a>";
            if ($start > 2) $html .= '<span class="page-dots">...</span>';
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $this->page) {
                $html .= "<span class=\"page-num active\">{$i}</span>";
            } else {
                $url = str_replace('{page}', $i, $urlPattern);
                $html .= "<a href=\"{$url}\" class=\"page-num\">{$i}</a>";
            }
        }

        if ($end < $this->totalPages) {
            if ($end < $this->totalPages - 1) $html .= '<span class="page-dots">...</span>';
            $url = str_replace('{page}', $this->totalPages, $urlPattern);
            $html .= "<a href=\"{$url}\" class=\"page-num\">{$this->totalPages}</a>";
        }

        // 下一页
        if ($this->hasNext()) {
            $url = str_replace('{page}', $this->page + 1, $urlPattern);
            $html .= "<a href=\"{$url}\" class=\"page-next\">下一页 &raquo;</a>";
        }

        $html .= '</nav>';
        return $html;
    }

    /**
     * 转数组（用于 API 响应）
     */
    public function toArray(): array
    {
        return [
            'data'         => $this->items,
            'current_page' => $this->page,
            'per_page'     => $this->perPage,
            'total'        => $this->total,
            'total_pages'  => $this->totalPages,
            'has_prev'     => $this->hasPrev(),
            'has_next'     => $this->hasNext(),
        ];
    }
}
