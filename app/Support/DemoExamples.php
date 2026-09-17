<?php

namespace App\Support;

use App\Admin\Forms\BalanceForm;
use App\Admin\Forms\BulkNoticeForm;
use App\Admin\Repositories\DemoRecordRepository;
use App\Models\DemoLog;
use App\Models\DemoRecord;
use App\Services\DemoData;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Woodynew\DcatAdminKit\Actions\Form\Copy;
use Woodynew\DcatAdminKit\Actions\Form\TopGoBack;
use Woodynew\DcatAdminKit\Actions\Form\TopSubmit;
use Woodynew\DcatAdminKit\Grid\Actions\TextActions;
use Woodynew\DcatAdminKit\Grid\RowActions\GridModalRowAction;
use Woodynew\DcatAdminKit\Grid\RowActions\OpenIFrameTab;
use Woodynew\DcatAdminKit\Grid\Tools\AdminGridHrefTool;
use Woodynew\DcatAdminKit\Grid\Tools\GridFormTool;
use Woodynew\DcatAdminKit\Support\AdminFormUtil;
use Woodynew\DcatAdminKit\Widgets\PostTable;

final class DemoExamples
{
    public const REPOSITORY_URL = 'https://github.com/woodynew/dcat-admin-kit';

    public const DOCS_URL = self::REPOSITORY_URL.'#components';

    public const DEMO_URL = 'https://github.com/woodynew/dcat-admin-kit-demo';

    public const BASE_DOCS_URL = 'https://learnku.com/docs/dcat-admin/2.x';

    public static function features(): array
    {
        return [
            'grid_defaults' => [__('表格默认行为'), __('隐藏查看、删除、批量删除和行选择器；工具按钮使用实心样式。'), 'preview'],
            'form_defaults' => [__('表单默认行为'), __('隐藏查看、删除、返回列表及继续查看、继续编辑选项。'), 'preview-form'],
            'show_defaults' => [__('详情默认行为'), __('隐藏详情页的编辑与删除按钮。'), 'preview-show'],
            'right_side_filter' => [__('右侧筛选器'), __('点击「筛选」查看收起的右侧滑出面板。'), 'preview'],
            'top_form_tools' => [__('表单顶部工具'), __('顶部出现原生「返回」「提交」工具；提交会实际保存。'), 'preview-form'],
            'back_to_top' => [__('回到顶部'), __('向下滚动超过半屏，右下角出现回到顶部按钮。'), 'preview'],
            'grid_assets' => [__('表格滚动增强'), __('固定表头样式、表格尺寸调整与本地 NiceScroll 横纵滚动条。'), 'preview'],
            'global_styles' => [__('全局布局样式'), __('观察侧栏、导航、内容区、表格及页脚的 Kit 样式变化。'), 'preview'],
        ];
    }

    public static function components(): array
    {
        return [
            'CopyQrCodeLink' => ['columns', __('二维码与复制'), 'copyColumn', __('formatter=null|Closure|string；display=null|Closure；width=200；height=200。本例使用默认 200×200 图片。短标签不改变复制的完整内容。'), 'column-displayers.md'],
            'MultiRow' => ['columns', __('多字段合并'), 'multiRowColumn', __('columns 为字段数组；formatter 接收字段名并绑定当前行。字段使用中文翻译，超过 30 字显示提示。'), 'column-displayers.md'],
            'TextAlert' => ['columns', __('长文本弹窗'), 'alertColumn', __('limit=15，before=1 保留开头；0 保留末尾。本例仅使用固定纯文本，Layer 会解释 HTML，不能直接传入用户输入。'), 'column-displayers.md'],
            'AfterLimit' => ['columns', __('尾部截取与展开'), 'afterColumn', __('limit=100，end="..."；本例保留末尾 24 字。点击双箭头展开、收起；标量内容自动转义。'), 'column-displayers.md'],
            'TextActions' => ['actions', __('文字行操作'), 'textActions', __('Grid::setActionClass(TextActions::class) 仅作用于此表格，保留 Dcat 原生查看、编辑、删除行为。'), 'actions-and-tools.md'],
            'AdminGridHrefTool' => ['actions', __('链接工具'), 'linkTool', __('title 为按钮标题，href 为站内目标 URL。Dcat 2.2.4 的 AbstractTool 输出 button，本例在应用内覆写 html 为原生链接，让工具真正跳转到创建页。'), 'actions-and-tools.md'],
            'GridFormTool' => ['actions', __('顶部通知表单'), 'noticeTool', __('title、action、formClass；普通 Widgets Form 从 data()->get("action") 读取操作标识。提交后查看下方通知日志。'), 'actions-and-tools.md'],
            'GridModalRowAction' => ['actions', __('行内余额弹窗'), 'balanceAction', __('title、action、formClass、params；传入当前记录 id，LazyWidget 从 payload 读取 id/action。提交后刷新余额和日志。'), 'actions-and-tools.md'],
            'OpenIFrameTab' => ['actions', __('标签页与普通跳转'), 'iframeAction', __('toUrl 必填；tabUrl 决定复用标识；title 为标签标题。普通操作页直接跳转，标签页实验室中打开/复用真实 iframe 标签。'), 'actions-and-tools.md'],
            'Copy' => ['records', __('复制为新记录'), 'copyTool', __('构造参数为创建页 URL。仅预填标题、链接、描述、状态；重新生成唯一编码，余额从 0 开始。点击复制不会立即写入。'), 'actions-and-tools.md'],
            'TopGoBack' => ['records', __('顶部返回'), 'topBackTool', __('无参数；iframe 环境优先返回活动页，普通页面使用浏览器历史。请先从记录列表进入表单。'), 'actions-and-tools.md'],
            'TopSubmit' => ['records', __('顶部提交'), 'topSubmitTool', __('无参数；触发同一张 Dcat 表单内原生 button.submit，执行相同验证与真实保存。'), 'actions-and-tools.md'],
            'AdminFormUtil' => ['records', __('请求形态判断'), 'formEvents', __('isCreatingEditing($form, ["title", "status"])；完整提交记录 true，列表快捷状态更新记录 false。仅判断请求形态，授权由中间件与服务完成。'), 'widgets-and-support.md'],
            'PostTable' => ['widgets', __('响应式汇总表'), 'postTable', __('header 为表头，data 为二维行数据。多个实例使用独立 DOM ID。本例对所有动态单元格先执行 e()。'), 'widgets-and-support.md'],
        ];
    }

    public static function copyColumn(Grid $grid): void
    {
        $grid->column('url', __('链接 / 复制完整内容'))->copyqrcodelink();
    }

    public static function multiRowColumn(Grid $grid): void
    {
        $grid->column('summary', __('紧凑信息'))->multirow(['title', 'code']);
    }

    public static function alertColumn(Grid $grid): void
    {
        $grid->column('safe_notice', __('固定纯文本说明'))->textalert(16, 1);
    }

    public static function afterColumn(Grid $grid): void
    {
        $grid->column('description', __('长文本 / 展开'))->afterlimit(24, '…');
    }

    public static function columnsGrid(): Grid
    {
        return Grid::make(null, function (Grid $grid) {
            // Fixed fixtures are never persisted and include empty and hostile-looking text.
            $grid->model()->setData(collect([
                ['id' => 1, 'title' => '中文链接与长文本', 'code' => 'KIT-CHINESE', 'url' => self::REPOSITORY_URL, 'description' => '这是一段可以展开和收起的中文说明。尾部截取会保留最后的内容，点击箭头即可阅读完整说明。', 'safe_notice' => '欢迎体验 Dcat Admin Kit。这里展示的是固定纯文本，点击即可查看完整说明。'],
                ['id' => 2, 'title' => '<img src=x onerror=alert(1)>', 'code' => '引号" & <标签>', 'url' => 'https://example.com/?q="<script>alert(1)</script>&中文=你好', 'description' => '<script>alert("不会执行")</script> & "双引号" \'单引号\' 中文🙂 — 特殊字符应该按文本显示。', 'safe_notice' => '这一行的其他单元格包含特殊字符，用来检查文本转义。弹窗只显示这段固定说明。'],
                ['id' => 3, 'title' => '空值边界', 'code' => 'KIT-EMPTY', 'url' => null, 'description' => '', 'safe_notice' => ''],
                ['id' => 4, 'title' => '短文本边界', 'code' => 'KIT-SHORT', 'url' => 'https://example.com', 'description' => '无需截断', 'safe_notice' => '简短说明'],
            ]));
            $grid->column('id', __('样例'));
            self::copyColumn($grid);
            self::multiRowColumn($grid);
            self::alertColumn($grid);
            self::afterColumn($grid);
            $grid->disableCreateButton();
            $grid->disableActions();
            $grid->disableRowSelector();
            $grid->disablePagination();
            $grid->disableFilter();
        });
    }

    public static function textActions(Grid $grid): void
    {
        $grid->setActionClass(TextActions::class);
    }

    public static function linkTool(Grid $grid): void
    {
        $grid->tools(function (Grid\Tools $tools) {
            $tools->append(new class(__('创建一条记录'), admin_url('demo/records/create')) extends AdminGridHrefTool
            {
                protected function html()
                {
                    // Dcat 2.2.4's tool base emits <button href>, which does not navigate.
                    $this->appendHtmlAttribute('class', $this->style);
                    $this->setHtmlAttribute('data-testid', 'grid-create-link');

                    return '<a '.$this->formatHtmlAttributes().'>'.e($this->title()).'</a>';
                }
            });
        });
    }

    public static function noticeTool(Grid $grid): void
    {
        $grid->tools(function (Grid\Tools $tools) {
            $tools->append(GridFormTool::make(__('发布演示通知'), 'notice', BulkNoticeForm::class));
        });
    }

    public static function balanceAction(Grid $grid): void
    {
        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->append(GridModalRowAction::make(__('增加余额'), 'balance', BalanceForm::class, [
                'id' => $actions->getKey(),
            ]));
        });
    }

    public static function iframeAction(Grid $grid): void
    {
        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $url = admin_url('demo/records/'.$actions->getKey());
            if (request()->query('iframe') === '1') {
                $url .= '?iframe=1';
            }
            $actions->append(new OpenIFrameTab(__('标签详情'), [
                'toUrl' => $url,
                'tabUrl' => $url,
                'title' => __('记录 #').$actions->getKey(),
            ]));
        });
    }

    public static function recordsGrid(bool $tools = true): Grid
    {
        return Grid::make(new DemoRecordRepository, function (Grid $grid) use ($tools) {
            $grid->setResource(admin_url('demo/records'));
            $grid->model()->orderByDesc('id');
            $grid->paginate(8);
            $grid->column('id', __('编号'))->sortable();
            $grid->column('title', __('标题'))->display(fn ($value) => e($value));
            $grid->column('code', __('唯一编码'))->display(fn ($value) => e($value));
            $grid->column('status', __('启用状态'))->switch('', true);
            $grid->column('balance', __('演示余额'))->display(fn ($value) => e($value));
            $grid->column('updated_at', __('更新时间'));
            $grid->filter(function (Grid\Filter $filter) {
                $filter->like('title', __('标题'));
                $filter->equal('status', __('启用状态'))->select([1 => __('启用'), 0 => __('停用')]);
            });
            if ($tools) {
                self::textActions($grid);
                self::linkTool($grid);
                self::noticeTool($grid);
                self::balanceAction($grid);
                self::iframeAction($grid);
            } elseif (request()->query('feature') === 'grid_assets') {
                self::wideGridLayout($grid);
            }
        });
    }

    public static function wideGridLayout(Grid $grid): void
    {
        // Native fixed-table layout supplies the .table-main required by Kit 0.1.0.
        // Zero fixed columns keeps one real table; both preview states use this layout.
        // height() 给容器一个固定高度，让宽表同时出现横向和纵向滚动条，
        // 用来检查两条滚动条的暗色适配。
        $grid->fixColumns(0, 0)->height(320);
        $grid->column('url', __('完整链接'))->display(fn ($value) => e($value))
            ->setAttributes(['style' => 'min-width: 360px']);
        $grid->column('description', __('完整描述'))->display(fn ($value) => e($value))
            ->setAttributes(['style' => 'min-width: 520px']);
    }

    public static function copyTool(Form $form): void
    {
        $form->tools(function (Form\Tools $tools) {
            if ($tools->form()->isEditing()) {
                $tools->append(new Copy(admin_url('demo/records/create?copy='.$tools->form()->getResourceId())));
            }
        });
    }

    public static function copyDefaults(?DemoRecord $source = null): array
    {
        return array_merge([
            'title' => '', 'url' => '', 'description' => '', 'status' => 1,
        ], $source ? $source->only(['title', 'url', 'description', 'status']) : [], [
            'code' => 'DEMO-'.Str::upper(Str::random(16)),
        ]);
    }

    public static function topBackTool(Form $form): void
    {
        $form->tools(function (Form\Tools $tools) {
            $tools->append(new TopGoBack);
        });
    }

    public static function topSubmitTool(Form $form): void
    {
        $form->tools(function (Form\Tools $tools) {
            $tools->append(new TopSubmit);
        });
    }

    public static function formEvents(Form $form): void
    {
        $fullSubmission = false;
        $form->saving(function (Form $form) use (&$fullSubmission) {
            $fullSubmission = AdminFormUtil::isCreatingEditing($form, ['title', 'status']);
        });
        $form->saved(function (Form $form, $result) use (&$fullSubmission) {
            if ($result) {
                app(DemoData::class)->log('form_shape',
                    'AdminFormUtil::isCreatingEditing = '.($fullSubmission ? 'true · 完整保存' : 'false · 局部状态更新'),
                    (int) $form->getKey());
                if (request()->query('iframe') === '1') {
                    $url = $form->getRedirectUrl($form->getKey());
                    if (is_string($url) && $url !== '') {
                        $url .= (str_contains($url, '?') ? '&' : '?').'iframe=1';

                        return $form->response()->success(__('保存成功'))->redirect($url);
                    }
                }
            }
        });
    }

    public static function recordForm(bool $tools = true, array $defaults = []): Form
    {
        return Form::make(new DemoRecordRepository, function (Form $form) use ($tools, $defaults) {
            // Keep Dcat's resource slicing independent of query parameters.
            if (request()->query('iframe') === '1') {
                $form->setResource(url(request()->path()));
                $form->setCurrentUrl(admin_url('demo/records?iframe=1'));
            }
            $form->text('title', __('标题'))->required()->rules('required|string|max:120')->default($defaults['title'] ?? '');
            $form->text('code', __('唯一编码'))->required()->rules('required|string|max:40|regex:/^[A-Za-z0-9_-]+$/')
                ->default($defaults['code'] ?? '')->help(__('编码必须唯一；复制为新记录时自动重新生成。'));
            $form->url('url', __('链接'))->rules('nullable|url:http,https|max:500')->default($defaults['url'] ?? '');
            $form->textarea('description', __('描述'))->rules('nullable|string|max:2000|not_regex:/[<>]/')->rows(5)
                ->default($defaults['description'] ?? '')->help(__('最多 2,000 字，纯文本，不接受 HTML 标签。'));
            $form->switch('status', __('启用状态'))->default($defaults['status'] ?? 1);
            if ($tools) {
                self::copyTool($form);
                self::topBackTool($form);
                self::topSubmitTool($form);
            }
            self::formEvents($form);
        });
    }

    public static function recordShow(int $id): Show
    {
        return Show::make($id, new DemoRecordRepository, function (Show $show) {
            $show->setResource(admin_url('demo/records'));
            $show->field('id', __('编号'));
            $show->field('title', __('标题'))->as(fn ($value) => e($value));
            $show->field('code', __('唯一编码'))->as(fn ($value) => e($value));
            $show->field('url', __('链接'))->as(fn ($value) => e($value));
            $show->field('description', __('描述'))->as(fn ($value) => e($value));
            $show->field('status', __('启用状态'))->as(fn ($value) => $value ? __('启用') : __('停用'));
            $show->field('balance', __('演示余额'));
            $show->field('created_at', __('创建时间'));
            $show->field('updated_at', __('更新时间'));
        });
    }

    public static function postTable(): PostTable
    {
        $rows = DemoRecord::query()->orderByDesc('id')->limit(6)->get()
            ->map(fn (DemoRecord $record) => [
                e($record->title), e($record->code), e($record->balance),
                e($record->status ? __('启用') : __('停用')),
            ])->all();

        return new PostTable([__('标题'), __('编码'), __('演示余额'), __('状态')], $rows);
    }

    public static function logs(): PostTable
    {
        $rows = DemoLog::query()->orderByDesc('id')->limit(12)->get()
            ->map(fn (DemoLog $log) => [
                e($log->created_at), e($log->action), e($log->demo_record_id ?? '—'), e($log->message),
            ])->all();

        return new PostTable([__('时间'), __('操作'), __('记录'), __('结果 / 消息')], $rows);
    }

    public static function source(string $method, string $class = self::class): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $lines = file($reflection->getFileName());

        return implode('', array_slice($lines, $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1));
    }

    public static function classSource(string $class): string
    {
        return file_get_contents((new ReflectionClass($class))->getFileName());
    }

    public static function imports(): string
    {
        $reflection = new ReflectionClass(self::class);
        $header = implode('', array_slice(file($reflection->getFileName()), 0, $reflection->getStartLine() - 1));

        return trim($header);
    }
}
