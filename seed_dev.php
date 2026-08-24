<?php
/**
 * NiurenBlog 测试数据填充（一次性 tinker 脚本）
 * 生成：12 篇动态（含长文/带图/纯文字）、多访客点赞、多层评论与回复
 */
use Illuminate\Support\Facades\DB;

$tables = [
    'posts'    => 'app_niuren_blog_posts',
    'comments' => 'app_niuren_blog_post_comments',
    'likes'    => 'app_niuren_blog_post_likes',
];
foreach ($tables as $t) {
    DB::table($t)->truncate();
}

$now = now();
$posts = [
    ['title' => null, 'content' => '今天天气真好，出去走走拍了几张照片～', 'images' => json_encode([
        '/Uploads/blog-seed/p1-1.jpg', '/Uploads/blog-seed/p1-2.jpg', '/Uploads/blog-seed/p1-3.jpg',
    ]), 'hours_ago' => 1],
    ['title' => null, 'content' => '路过。。', 'images' => null, 'hours_ago' => 3],
    ['title' => '夜跑打卡', 'content' => '今晚 5 公里完成，配速 5\'30"，坚持第 42 天。跑步这件事，一旦开始就停不下来了。今天的风很舒服，路灯下的影子拉得很长，有一种城市夜晚独有的安静。', 'images' => null, 'hours_ago' => 8],
    ['title' => null, 'content' => '中午吃了一碗超好吃的牛肉面，汤头浓郁，面条劲道，强烈推荐公司楼下那家老字号！', 'images' => json_encode(['/Uploads/blog-seed/p4-1.jpg']), 'hours_ago' => 14],
    ['title' => '读《置身事内》有感', 'content' => '最近读完了这本书，对地方政府的经济行为有了全新的认识。书里讲到的土地财政、招商引资模式，解释了很多我们习以为常的现象背后的逻辑。推荐给想理解中国经济运转机制的朋友。', 'images' => null, 'hours_ago' => 26],
    ['title' => null, 'content' => '周末去了趟海边，落日太美了，词穷，直接上图。', 'images' => json_encode([
        '/Uploads/blog-seed/p6-1.jpg', '/Uploads/blog-seed/p6-2.jpg',
    ]), 'hours_ago' => 40],
    ['title' => null, 'content' => '写了一个通宵的代码，终于把那个 bug 修好了。原来是并发场景下的竞态条件，加了分布式锁之后世界清净了。程序员的快乐就是这么朴实无华。', 'images' => null, 'hours_ago' => 50],
    ['title' => null, 'content' => '家里的猫今天终于肯让我抱了，感动哭。', 'images' => json_encode(['/Uploads/blog-seed/p8-1.jpg', '/Uploads/blog-seed/p8-2.jpg', '/Uploads/blog-seed/p8-3.jpg', '/Uploads/blog-seed/p8-4.jpg']), 'hours_ago' => 60],
    ['title' => null, 'content' => '今天学会了一道新菜：番茄炖牛腩。步骤记录一下，牛腩焯水后炒糖色，加番茄炒出沙，炖一个半小时，最后大火收汁。下次试试加点土豆。', 'images' => null, 'hours_ago' => 76],
    ['title' => null, 'content' => '又是加班的一天，凌晨的办公室只有我和保洁阿姨。', 'images' => null, 'hours_ago' => 100],
    ['title' => null, 'content' => '陪爸妈去公园散步，他们走得慢，但笑容多了。多陪陪家人，比什么都重要。今天爸说了很多以前的事，我才发现我对他们的了解太少了。时光慢些吧。', 'images' => null, 'hours_ago' => 124],
    ['title' => null, 'content' => '新的一月，立个 flag：早睡早起、每周读一本书、坚持运动。虽然大概率月底会打脸，但仪式感还是要有的。', 'images' => null, 'hours_ago' => 148],
];

$postIds = [];
foreach ($posts as $p) {
    $time = $now->copy()->subHours($p['hours_ago'])->format('Y-m-d H:i:s');
    $postIds[] = DB::table($tables['posts'])->insertGetId([
        'title'       => $p['title'],
        'content'     => $p['content'],
        'images'      => $p['images'],
        'status'      => 1,
        'create_time' => $time,
        'update_time' => $time,
    ]);
}

// 点赞：昵称多样化，含博主
$likePlan = [
    0 => ['路人', '小明', '小王'],
    1 => ['路人'],
    2 => ['小明', '小王', '阿强', 'Vicky'],
    3 => ['路人', 'Vicky'],
    4 => ['阿强'],
    5 => ['路人', '小明', '小王', '阿强', 'Vicky'],
    6 => ['小明'],
    7 => ['路人', 'Vicky'],
    8 => ['小王'],
    9 => [],
    10 => ['路人', '小明'],
    11 => [],
];
$likeRows = [];
foreach ($likePlan as $idx => $names) {
    foreach ($names as $i => $name) {
        $likeRows[] = [
            'post_id'     => $postIds[$idx],
            'visitor_id'  => 'v_seed_' . md5($name),
            'nickname'    => $name,
            'create_time' => $now->copy()->subHours($posts[$idx]['hours_ago'])->addMinutes(10 + $i * 7)->format('Y-m-d H:i:s'),
        ];
    }
}
if ($likeRows) { DB::table($tables['likes'])->insert($likeRows); }

// 评论：普通评论 + 回复链（含博主回复、游客互回复）
$commentPlan = [
    // post 0
    0 => [
        ['路人', '照片拍得真不错，这是什么地方？', null, 'email' => 'passerby@qq.com', 'website' => ''],
        ['小明', '第二张构图绝了！', null, 'email' => '', 'website' => 'https://xiaoming.me'],
    ],
    // post 1（“路过。。”）
    1 => [
        ['路人22', '哈哈真的是纯路过', null, 'email' => '', 'website' => ''],
    ],
    // post 2（夜跑）
    2 => [
        ['阿强', '42 天了，太自律了吧！我跑三天就放弃了', null, 'email' => 'aq@163.com', 'website' => ''],
        ['博主', '坚持前两周最难，熬过去就成习惯了，一起加油！', 'reply:阿强:0', 'email' => '', 'website' => ''],
        ['阿强', '好，明天开始重新跑！', 'reply:博主:1', 'email' => 'aq@163.com', 'website' => ''],
        ['Vicky', '配速 5\'30 很强了，我 6 分半还喘成狗', null, 'email' => '', 'website' => ''],
    ],
    // post 3（牛肉面）
    3 => [
        ['小王', '求地址！我也想去吃', null, 'email' => 'wang@126.com', 'website' => ''],
        ['路人', '看着就香，晚饭还没吃的我饿了', null, 'email' => '', 'website' => ''],
        ['小明', '这家我知道，他家泡馍也不错', null, 'email' => '', 'website' => 'https://xiaoming.me'],
    ],
    // post 4（读书）
    4 => [
        ['Vicky', '这本书在我书单里躺了半年了……看完你的感想决定这周就读', null, 'email' => '', 'website' => ''],
    ],
    // post 5（海边落日）
    5 => [
        ['小明', '这落日可以直接当壁纸了', null, 'email' => '', 'website' => ''],
        ['小王', '词穷就对了，美景本来就不需要语言', null, 'email' => '', 'website' => ''],
        ['路人', '羡慕住在海边的人', null, 'email' => 'passerby@qq.com', 'website' => ''],
    ],
    // post 6（修 bug）
    6 => [
        ['阿强', '竞态条件 + 分布式锁，经典组合拳。同程序员，深有同感，凌晨修完 bug 的那种成就感无可替代。', null, 'email' => '', 'website' => ''],
        ['小明', '哈哈哈哈程序员的快乐就是这么朴实无华且枯燥', null, 'email' => '', 'website' => ''],
    ],
    // post 7（猫）
    7 => [
        ['Vicky', '猫猫可爱！什么品种呀', null, 'email' => '', 'website' => ''],
        ['博主', '英短蓝猫，两岁了，平时高冷得很', 'reply:Vicky:0', 'email' => '', 'website' => ''],
    ],
    // post 8（做菜）
    8 => [
        ['小王', '记下了，周末试试。加土豆建议炸一下再炖，更香', null, 'email' => '', 'website' => ''],
    ],
    // post 9（加班）
    9 => [
        ['路人', '抱抱，注意身体', null, 'email' => '', 'website' => ''],
    ],
    // post 10（陪爸妈）
    10 => [
        ['小明', '看得有点想家了，这周末也回去陪陪爸妈', null, 'email' => '', 'website' => ''],
        ['Vicky', '时光慢些吧，说到心坎里了', null, 'email' => '', 'website' => ''],
    ],
    // post 11（立 flag）
    11 => [
        ['阿强', '坐等月底打脸现场', null, 'email' => '', 'website' => ''],
        ['博主', '你礼貌吗？', 'reply:阿强:0', 'email' => '', 'website' => ''],
    ],
];

foreach ($commentPlan as $idx => $comments) {
    $baseTime = $now->copy()->subHours($posts[$idx]['hours_ago']);
    $idMap = []; // 昵称(全局序号) => comment id
    $seq = 0;
    foreach ($comments as $c) {
        $time = $baseTime->copy()->addMinutes(15 + $seq * 12)->format('Y-m-d H:i:s');
        $replyToId = null;
        if (is_string($c[2]) && strpos($c[2], 'reply:') === 0) {
            $replyToId = $idMap[$c[2]] ?? null;
        }
        $cid = DB::table($tables['comments'])->insertGetId([
            'post_id'     => $postIds[$idx],
            'reply_to_id' => $replyToId,
            'visitor_id'  => $c[0] === '博主' ? 'v_seed_owner' : 'v_seed_c_' . md5($c[0]),
            'nickname'    => $c[0],
            'content'     => $c[1],
            'email'       => $c[3] ?? '',
            'website'     => $c[4] ?? '',
            'create_time' => $time,
        ]);
        $idMap['reply:' . $c[0] . ':' . $seq] = $cid;
        $seq++;
    }
}

echo 'posts: ' . count($postIds) . ', likes: ' . count($likeRows) . ', comments: ' . DB::table($tables['comments'])->count() . PHP_EOL;
