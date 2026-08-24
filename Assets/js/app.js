/**
 * 朋友圈博客 前台交互脚本（v1.4.0）
 * 依赖：jQuery（页面已引入 layui 自带 jQuery）
 * 职责：
 *  1. 注入 X-Visitor-Id / X-CSRF-TOKEN 请求头（访客身份三级策略）
 *  2. 顶部导航滚动渐变 + 封面视差 + 深浅色主题切换
 *  3. 点赞（···浮层/卡片）乐观更新 + 回滚，昵称随身份传递
 *  4. 评论区懒加载 + 底部评论输入条发送（昵称必填，邮箱/网址选填，Cookie 自动记忆）
 *  5. emoji 表情面板（复用 CmsproComment 五类 110 个表情）
 *  6. 九宫格长图标注 + 全屏图片浏览
 *  7. 写动态页：图片选择/上传/删除/发表 + 发布密码验证弹窗
 */
(function (window) {
    'use strict';

    function boot($) {

        var VISITOR_KEY = 'nr_visitor_id';
        var COOKIE_NAME = 'nr_visitor';
        var THEME_KEY = 'nr_theme';
        var COMMENT_META_COOKIE = 'nr_comment_meta';
        // 前台基础路径（path 模式为前缀如 /blog，root/domain 模式为空串），视图注入
        var NR_BASE = window.NR_BASE || '';

        /* -------------------------------------------------------------------
         * 访客身份：localStorage 优先，其次 Cookie，最后由服务端下发补齐
         * ------------------------------------------------------------------- */
        function readCookie(name) {
            var parts = document.cookie ? document.cookie.split('; ') : [];
            for (var i = 0; i < parts.length; i++) {
                var idx = parts[i].indexOf('=');
                if (parts[i].slice(0, idx) === name) {
                    return decodeURIComponent(parts[i].slice(idx + 1));
                }
            }
            return '';
        }

        function getVisitorId() {
            if (!window.__nrVisitorId) {
                window.__nrVisitorId = window.localStorage.getItem(VISITOR_KEY)
                    || readCookie(COOKIE_NAME)
                    || '';
            }
            return window.__nrVisitorId;
        }

        function setVisitorId(id) {
            if (!id) { return; }
            window.__nrVisitorId = id;
            try { window.localStorage.setItem(VISITOR_KEY, id); } catch (e) { /* 隐私模式忽略 */ }
        }

        /* -------------------------------------------------------------------
         * AJAX 全局配置：访客头 + CSRF
         * ------------------------------------------------------------------- */
        $.ajaxSetup({
            headers: {
                'X-Visitor-Id': getVisitorId(),
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''
            },
            xhrFields: { withCredentials: true }
        });

        $(function () {
            var cookieVal = readCookie(COOKIE_NAME);
            if (cookieVal && !window.localStorage.getItem(VISITOR_KEY)) {
                setVisitorId(cookieVal);
            }
            initTheme();
            initScrollEffects();
            formatTimes();
            markLongImages();
            initCommentMemory();
            initEmoji();
            initPasswordGate();

            // feed 页：评论默认展开（限量渲染，超出部分点击「展开更多评论」加载）
            // + 滚动到底 AJAX 自动加载下一页动态
            if ($('body').data('page') === 'feed') {
                initFeedScroll();
                $('.mom-card').each(function () { loadComments($(this)); });
            }
        });

        /* -------------------------------------------------------------------
         * 工具函数
         * ------------------------------------------------------------------- */
        function escapeHtml(text) {
            return $('<div/>').text(text == null ? '' : String(text)).html();
        }

        function unwrap(res) {
            if (res && typeof res === 'object') {
                if ('code' in res) {
                    if (res.code !== 0 && res.code !== '0') {
                        throw new Error(res.msg || res.message || '操作失败');
                    }
                    return res.data != null ? res.data : {};
                }
                return res;
            }
            throw new Error('响应格式异常');
        }

        function request(url, method, payload) {
            return $.ajax({
                url: url,
                type: method,
                dataType: 'json',
                data: payload || {}
            }).then(function (res) {
                return unwrap(res);
            });
        }

        var pad = function (n) { return n < 10 ? '0' + n : '' + n; };

        /* -------------------------------------------------------------------
         * 相对时间展示（描述 4.4）：<=1分钟 刚刚 / 今天12:30 / 昨天 / MM-DD / 跨年含年份
         * ------------------------------------------------------------------- */
        function formatTime(value) {
            if (!value) { return ''; }
            var ts;
            if (/^\d{10}$/.test(String(value))) {
                ts = new Date(value * 1000);
            } else {
                ts = new Date(String(value).replace('T', ' ').replace(/-/g, '/').slice(0, 19));
            }
            if (isNaN(ts.getTime())) { return String(value).slice(0, 16); }

            var now = new Date();
            var diff = Math.floor((now.getTime() - ts.getTime()) / 1000);

            if (diff < 60) { return '刚刚'; }
            if (diff < 3600) { return Math.floor(diff / 60) + ' 分钟前'; }
            if (diff < 7200) { return '1 小时前'; }

            var isYesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate()) -
                new Date(ts.getFullYear(), ts.getMonth(), ts.getDate()) === 86400000;

            if (isYesterday) { return '昨天'; }
            if (ts.getFullYear() === now.getFullYear()
                && ts.getMonth() === now.getMonth()
                && ts.getDate() === now.getDate()) {
                return '今天 ' + pad(ts.getHours()) + ':' + pad(ts.getMinutes());
            }
            if (ts.getFullYear() === now.getFullYear()) {
                return (ts.getMonth() + 1) + '-' + pad(ts.getDate());
            }
            return ts.getFullYear() + '-' + pad(ts.getMonth() + 1) + '-' + pad(ts.getDate());
        }

        function formatTimes() {
            $('.mom-time').each(function () {
                var el = $(this).attr('data-time');
                if (!el) { return; }
                $(this).text(formatTime(el));
            });
        }

        /* -------------------------------------------------------------------
         * 评论元数据 Cookie 记忆：昵称/邮箱/网址 自动填充
         * ------------------------------------------------------------------- */
        function readCommentMeta() {
            var meta = {};
            try {
                var raw = readCookie(COMMENT_META_COOKIE);
                if (raw) { meta = JSON.parse(decodeURIComponent(raw)); }
            } catch (e) { meta = {}; }
            return meta || {};
        }

        function saveCommentMeta(meta) {
            var value = encodeURIComponent(JSON.stringify(meta));
            var expires = new Date(Date.now() + 365 * 24 * 3600 * 1000).toUTCString();
            document.cookie = COMMENT_META_COOKIE + '=' + value + '; expires=' + expires + '; path=/';
        }

        /**
         * 当前访客昵称：博主（已验证密码/后台登录）返回博客名称；
         * 普通访客取评论输入框昵称（发送评论后 Cookie 记忆），均无则「访客」
         */
        function currentVisitorNickname() {
            var owner = $.trim(window.NR_OWNER_NICKNAME || '');
            if (owner) { return owner; }
            var fromInput = $.trim($('.mom-comment-nick').val() || '');
            if (fromInput) { return fromInput; }
            return $.trim(readCommentMeta().nickname || '') || '访客';
        }

        function initCommentMemory() {
            if (!$('.mom-comment-meta').length) { return; }
            var meta = readCommentMeta();
            // 博主（已验证发布密码或后台登录）昵称自动填充博客名称，优先于访客记忆昵称
            var owner = $.trim(window.NR_OWNER_NICKNAME || '');
            if (owner) {
                $('.mom-comment-nick').val(owner);
            } else if (meta.nickname) {
                $('.mom-comment-nick').val(meta.nickname);
            }
            if (meta.email) { $('.mom-comment-email').val(meta.email); }
            if (meta.website) { $('.mom-comment-website').val(meta.website); }
        }

        /* -------------------------------------------------------------------
         * emoji 表情面板（复用 CmsproComment 五类 110 个表情）
         * ------------------------------------------------------------------- */
        var EMOJI_CATEGORIES = [
            { name: '常用', emojis: ['😀','😂','🤣','😊','😍','🥰','😘','😜','😝','🤗','🤔','😎','🥳','😏','😢','😭','😤','😡','🤯','😱','🤮','🥴','😴','🤤','😈','👿','💀','👻','👽','🤖'] },
            { name: '手势', emojis: ['👍','👎','👌','✌️','🤞','🤟','🤘','🤙','👋','🤚','✋','🖖','👏','🙌','🤝','🙏','💪','🤛','🤜','🖕'] },
            { name: '爱心', emojis: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','♥️'] },
            { name: '自然', emojis: ['☀️','🌙','⭐','🌟','💫','🔥','💧','🌊','🌈','🌸','🌺','🌻','🌹','🍀','🍁','🍂','🍃','🌵','🎄','🌲'] },
            { name: '物品', emojis: ['🎉','🎊','🎁','🎈','🎀','🏆','🥇','🏅','🎯','🎮','🎲','🎵','🎶','🎸','🎹','🎺','🎻','🎬','📷','💻'] }
        ];

        function initEmoji() {
            var $panel = $('.mom-comment-emoji-panel');
            if (!$panel.length) { return; }
            var activeIndex = 0;

            function renderCategory(index) {
                activeIndex = index;
                var cat = EMOJI_CATEGORIES[index];
                var html = '<div class="mom-emoji-tabs">';
                for (var i = 0; i < EMOJI_CATEGORIES.length; i++) {
                    html += '<span class="mom-emoji-tab' + (i === index ? ' active' : '') + '" data-idx="' + i + '">'
                        + EMOJI_CATEGORIES[i].name + '</span>';
                }
                html += '</div><div class="mom-emoji-content">';
                for (var j = 0; j < cat.emojis.length; j++) {
                    html += '<span class="mom-emoji-item" data-emoji="' + cat.emojis[j] + '">' + cat.emojis[j] + '</span>';
                }
                html += '</div>';
                $panel.html(html);
            }

            function togglePanel(btn) {
                if ($panel.is('[hidden]')) {
                    // 先显示再测量：hidden 状态下 outerHeight/outerWidth 为 0，会导致定位错乱
                    $panel.removeAttr('hidden');
                    // panel 为 position:fixed，直接使用视口坐标（getBoundingClientRect 同系）
                    var btnRect = $(btn)[0].getBoundingClientRect();
                    var pw = $panel.outerWidth();
                    var ph = $panel.outerHeight();
                    var viewW = document.documentElement.clientWidth;

                    // 显示在表情按钮上方：面板右边缘对齐按钮右边缘，且不超出视口
                    var left = Math.min(Math.max(btnRect.right - pw, 8), viewW - pw - 8);
                    var top = btnRect.top - ph - 8;
                    if (top < 8) { top = btnRect.bottom + 8; }

                    $panel.css({ left: left + 'px', top: top + 'px' });
                } else {
                    $panel.attr('hidden', '');
                }
            }

            renderCategory(0);

            $(document).on('click', '.mom-comment-emoji', function (e) {
                e.stopPropagation();
                togglePanel(this);
            });

            $panel.on('click', '.mom-emoji-tab', function (e) {
                e.stopPropagation();
                renderCategory(parseInt($(this).attr('data-idx'), 10));
            });

            $panel.on('click', '.mom-emoji-item', function () {
                var $input = $('.mom-comment-input');
                var emoji = $(this).attr('data-emoji');
                if ($input.length) {
                    var el = $input[0];
                    var start = el.selectionStart || 0;
                    var value = el.value;
                    el.value = value.substring(0, start) + emoji + value.substring(el.selectionEnd || 0);
                    el.selectionStart = el.selectionEnd = start + emoji.length;
                    $input.trigger('input');
                    el.focus();
                }
                $panel.attr('hidden', '');
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('.mom-comment-emoji, .mom-comment-emoji-panel').length) {
                    $panel.attr('hidden', '');
                }
            });
        }

        /* -------------------------------------------------------------------
         * 发布密码验证弹窗（写动态页）
         * ------------------------------------------------------------------- */
        function initPasswordGate() {
            var isWriter = $('body[data-page="writer"]').length > 0;
            // 密码门独立页：未验证访客的服务端入口，页面仅有密码弹窗
            var isGate = $('body[data-page="pwd-gate"]').length > 0;
            if (!isWriter && !isGate) { return; }
            var $mask = $('.mom-pwd-mask');
            if (!$mask.length) { return; }

            if (isGate) {
                // 门页：弹窗常显，验证成功后跳转写动态页（Cookie 已下发，服务端放行）
                $mask.on('click', '.mom-pwd-confirm', function () {
                    var pwd = $.trim($mask.find('.mom-pwd-input').val() || '');
                    if (!pwd) { alert('请输入发布密码'); return; }
                    request(window.NR_VERIFY_URL, 'POST', { password: pwd })
                        .done(function () {
                            window.location.href = window.NR_WRITE_URL || (NR_BASE + '/write');
                        })
                        .fail(function (xhr) {
                            alert((xhr.responseJSON && (xhr.responseJSON.msg || xhr.responseJSON.message)) || '密码验证失败');
                            $mask.find('.mom-pwd-input').val('').focus();
                        });
                });
                $mask.on('keydown', '.mom-pwd-input', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); $mask.find('.mom-pwd-confirm').trigger('click'); }
                });
                $mask.on('click', '.mom-pwd-cancel', function () {
                    window.location.href = window.NR_LIST_URL || (NR_BASE + '/');
                });
                setTimeout(function () { $mask.find('.mom-pwd-input').focus(); }, 60);
                return;
            }

            if (!window.NR_PWD_REQUIRED) { return; }
            $mask.removeAttr('hidden');
            setTimeout(function () { $mask.find('.mom-pwd-input').focus(); }, 60);

            $mask.on('click', '.mom-pwd-cancel', function () {
                window.location.href = window.NR_LIST_URL || (NR_BASE + '/');
            });

            $mask.on('click', '.mom-pwd-confirm', function () {
                var pwd = $.trim($mask.find('.mom-pwd-input').val() || '');
                if (!pwd) { alert('请输入发布密码'); return; }
                request(window.NR_VERIFY_URL, 'POST', { password: pwd })
                    .done(function () {
                        $mask.attr('hidden', '');
                    })
                    .fail(function (xhr) {
                        alert((xhr.responseJSON && (xhr.responseJSON.msg || xhr.responseJSON.message)) || '密码验证失败');
                        $mask.find('.mom-pwd-input').val('').focus();
                    });
            });

            $mask.on('keydown', '.mom-pwd-input', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); $mask.find('.mom-pwd-confirm').trigger('click'); }
            });
        }

        /* -------------------------------------------------------------------
         * 主题切换 + 导航滚动 + 封面视差
         * ------------------------------------------------------------------- */
        function initTheme() {
            // 主题优先级：访客本地选择 > 后台「显示模式」默认（服务端已直出到 <html data-theme>）
            var saved = '';
            try { saved = window.localStorage.getItem(THEME_KEY) || ''; } catch (e) { /* ignore */ }
            if (saved === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (saved === 'light') {
                document.documentElement.removeAttribute('data-theme');
            }
            syncThemeIcon();
        }

        function syncThemeIcon() {
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            $('.mom-theme-toggle i')
                .toggleClass('fa-moon-o', !dark)
                .toggleClass('fa-sun-o', dark);
        }

        $(document).on('click', '.mom-theme-toggle', function () {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var next = isDark ? 'light' : 'dark';
            if (next === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }
            try { window.localStorage.setItem(THEME_KEY, next); } catch (e) { /* ignore */ }
            syncThemeIcon();
        });

        function initScrollEffects() {
            var $nav = $('.mom-nav');
            var $cover = $('.mom-cover');

            function onScroll() {
                var y = window.pageYOffset || 0;
                if (y > 8 && $nav.length && !$nav.hasClass('is-solid')) {
                    $nav.addClass('is-scrolled');
                } else if (y <= 8 && $nav.length) {
                    $nav.removeClass('is-scrolled');
                }
                // 封面视差：下拉时封面随滚动放大
                if ($cover.length && y < 0) {
                    var scale = 1 + (-y) / 300;
                    $cover.css('transform', 'scale(' + scale + ')');
                    $cover.css('transform-origin', 'top center');
                } else if ($cover.length) {
                    $cover.css('transform', '');
                }
            }
            $(window).on('scroll', onScroll).on('touchmove', onScroll);
        }

        /* -------------------------------------------------------------------
         * 图片：超长图标注（单图）+ 全屏浏览
         * ------------------------------------------------------------------- */
        function markLongImages() {
            $('.mom-grid-1 .mom-grid-cell img').each(function () {
                var img = this;
                if (img.complete) { checkRatio(); }
                else { $(img).on('load', checkRatio); }
                function checkRatio() {
                    var r = (img.naturalHeight || 0) / Math.max((img.naturalWidth || 1), 1);
                    if (r > 3) { $(img).closest('.mom-grid-cell').addClass('is-long'); }
                }
            });
        }

        var viewerImages = [];
        var viewerIndex = 0;

        function openViewer(list, idx) {
            if (!list.length) { return; }
            viewerImages = list;
            viewerIndex = idx;
            var $v = $('.mom-viewer');
            $v.find('img').attr('src', list[idx]);
            $v.removeAttr('hidden').removeClass('zooming');
            $(document.body).css('overflow', 'hidden');
        }

        function viewerShow(idx) {
            viewerIndex = (idx + viewerImages.length) % viewerImages.length;
            $('.mom-viewer img').attr('src', viewerImages[viewerIndex]);
            $('.mom-viewer').removeClass('zooming');
        }

        function closeViewer() {
            $('.mom-viewer').attr('hidden', '').removeClass('zooming');
            $('.mom-viewer img').attr('src', '');
            $(document.body).css('overflow', '');
        }

        $(document).on('click', '.mom-grid-cell img', function () {
            var $imgs = $(this).closest('.mom-grid').find('.mom-grid-cell img');
            var urls = [];
            $imgs.each(function () { urls.push($(this).attr('data-full') || this.src); });
            // 按 DOM 位置定位（data-full 为相对路径，与 this.src 绝对地址无法直接 indexOf）
            var idx = $imgs.index(this);
            openViewer(urls, idx < 0 ? 0 : idx);
        });

        $(document).on('click', '.mom-viewer-close', closeViewer);
        $(document).on('click', '.mom-viewer-next', function () { viewerShow(viewerIndex + 1); });
        $(document).on('click', '.mom-viewer-prev', function () { viewerShow(viewerIndex - 1); });

        // 点击图片 / 双击 放大 / 还原
        $(document).on('dblclick', '.mom-viewer img', function () {
            $('.mom-viewer').toggleClass('zooming');
        });

        // 点击空白区域关闭
        $(document).on('click', function (e) {
            if ($(e.target).hasClass('mom-viewer')) { closeViewer(); }
        });

        // 键盘左右切换、Esc 关闭
        $(document).on('keydown', function (e) {
            if ($('.mom-viewer').is('[hidden]')) { return; }
            if (e.key === 'ArrowLeft') { viewerShow(viewerIndex - 1); }
            else if (e.key === 'ArrowRight') { viewerShow(viewerIndex + 1); }
            else if (e.key === 'Escape') { closeViewer(); }
        });

        /* -------------------------------------------------------------------
         * 点赞
         * ------------------------------------------------------------------- */
        /**
         * 更新点赞行：图标高亮 + 点赞人昵称列表（💗 路人、小明、小王）
         * likers 为空时隐藏整个点赞行内容；昵称为空回退「访客」
         */
        function setLiked(postId, liked, likers) {
            var $card = $('.mom-card[data-post-id="' + postId + '"]');
            $card.find('.mom-like-row').toggleClass('liked', liked);
            var $nick = $card.find('.mom-like-nick');
            var names = (likers || []).map(function (name) {
                return $.trim(name) || '访客';
            });
            if (names.length) {
                if (!$nick.length) {
                    $card.find('.mom-like-icon').after('<span class="mom-like-nick"></span>');
                    $nick = $card.find('.mom-like-nick');
                }
                $nick.text(names.join('、'));
            } else {
                $nick.remove();
            }
        }

        function toggleLike(postId, $btn) {
            var $card = $('.mom-card[data-post-id="' + postId + '"]');
            var wasLiked = $card.find('.mom-like-row').hasClass('liked');
            // 点赞仅一次：已赞不再发起请求（后端亦幂等保留）
            if (wasLiked) { return; }
            // 乐观更新：本地昵称即时上屏，响应到达后以服务端列表为准
            var optimisticLikers = ($card.find('.mom-like-nick').text() || '').split('、')
                .filter(function (name) { return $.trim(name); });
            optimisticLikers.push(currentVisitorNickname() || '访客');
            setLiked(postId, true, optimisticLikers);

            request(NR_BASE + '/like', 'POST', { post_id: postId, nickname: currentVisitorNickname() })
                .done(function (data) {
                    setLiked(postId, !!data.liked, data.likers);
                })
                .fail(function (xhr) {
                    // 回滚：恢复原状态（取消乐观插入的昵称）
                    setLiked(postId, false, optimisticLikers.slice(0, -1));
                    alert((xhr.responseJSON && (xhr.responseJSON.msg || xhr.responseJSON.message)) || '点赞失败，请稍后再试');
                });
        }

        // ··· 浮层：展示时定位到触发按钮，收起时淡出（同一时刻仅一个）
        $(document).on('click', '.mom-more', function () {
            var $btn = $(this);
            var $pop = $btn.closest('.mom-card').find('> .mom-pop');
            var visible = $pop.hasClass('show');
            hidePop();
            if (!visible) { showPop($pop, $btn); }
        });

        function showPop($pop, $btn) {
            var rect = $btn[0].getBoundingClientRect();
            $pop.removeAttr('hidden').addClass('show');
            var pw = $pop.outerWidth();
            var ph = $pop.outerHeight();
            var left = Math.min(Math.max(rect.right - pw, 8), window.innerWidth - pw - 8);
            // 优先显示在触发按钮下方，底部不足时翻转到按钮上方
            var top = rect.bottom + 6;
            if (top + ph > window.innerHeight - 8) { top = rect.top - ph - 6; }
            $pop.css({ left: left + 'px', top: top + 'px' });
        }

        function hidePop($pop) {
            $pop = $pop || $('.mom-pop');
            $pop.removeClass('show');
            setTimeout(function () { if (!$pop.hasClass('show')) { $pop.attr('hidden', ''); } }, 180);
        }

        // 点赞：收起浮层并执行点赞
        $(document).on('click', '.mom-pop-like', function () {
            var $card = $(this).closest('.mom-card');
            var postId = $card.attr('data-post-id');
            hidePop($card.find('> .mom-pop'));
            toggleLike(postId, $(this));
        });

        // 点击点赞行的爱心图标：直接点赞/取消（与浮层「赞」等效）
        $(document).on('click', '.mom-like-icon', function () {
            var $card = $(this).closest('.mom-card');
            var postId = $card.attr('data-post-id');
            if (!postId) { return; }
            toggleLike(postId, $(this));
        });

        // 点击浮层/触发按钮之外的区域收起
        $(document).on('click', function (e) {
            if ($(e.target).closest('.mom-pop, .mom-more').length) { return; }
            hidePop();
        });

        /* -------------------------------------------------------------------
         * 微信风格居中确认弹窗（1:1 复刻微信 iOS 删除确认，PC/移动统一）
         * 居中白色圆角弹窗：标题 + 说明 + 左「取消」右红色「删除」，点遮罩/取消关闭
         * ------------------------------------------------------------------- */
        var $wxDialog = null;

        function hideWxDialog() {
            if (!$wxDialog) { return; }
            var $mask = $wxDialog;
            $wxDialog = null;
            $mask.removeClass('show');
            setTimeout(function () { $mask.remove(); }, 250);
        }

        function showWxDeleteDialog(onConfirm) {
            hideWxDialog();
            $wxDialog = $(
                '<div class="wx-dialog-mask">'
                + '<div class="wx-dialog" role="alertdialog" aria-modal="true" aria-label="删除动态">'
                + '<div class="wx-dialog-title">删除</div>'
                + '<div class="wx-dialog-content">是否删除该动态？删除后该动态的点赞和评论将一并删除</div>'
                + '<div class="wx-dialog-footer">'
                + '<button type="button" class="wx-dialog-btn wx-dialog-cancel">取消</button>'
                + '<button type="button" class="wx-dialog-btn wx-dialog-danger">删除</button>'
                + '</div>'
                + '</div>'
                + '</div>'
            );
            $(document.body).append($wxDialog);
            // 先渲染再添加 show 类，确保缩放淡入过渡动画生效
            setTimeout(function () { $wxDialog.addClass('show'); }, 20);

            // 点击遮罩关闭（点在弹窗上不关闭）
            $wxDialog.on('click', function (e) {
                if (e.target === this) { hideWxDialog(); }
            });
            $wxDialog.find('.wx-dialog-cancel').on('click', hideWxDialog);
            $wxDialog.find('.wx-dialog-danger').on('click', function () {
                hideWxDialog();
                onConfirm();
            });
        }

        // 管理员删除动态：微信风格居中弹窗二次确认，成功移除卡片
        $(document).on('click', '.mom-post-delete', function () {
            var $card = $(this).closest('.mom-card');
            var postId = $card.data('post-id');
            if (!postId) { return; }

            showWxDeleteDialog(function () {
                request(NR_BASE + '/posts/' + postId, 'DELETE')
                    .done(function () {
                        $card.fadeOut(200, function () { $(this).remove(); });
                    })
                    .fail(function (xhr) {
                        alert((xhr.responseJSON && (xhr.responseJSON.msg || xhr.responseJSON.message)) || '删除失败，请稍后再试');
                    });
            });
        });

        /* -------------------------------------------------------------------
         * 动态流：滚动到底 AJAX 自动加载下一页（首屏服务端渲染）
         * ------------------------------------------------------------------- */
        var feedState = { page: 1, hasMore: false, loading: false, everMore: false };

        /** 渲染单张动态卡片（结构与 Blade 首屏模板一致） */
        function renderCard(item) {
            var name = window.NR_BLOG_NAME || '朋友圈';
            var avatar = window.NR_BLOG_AVATAR || '';
            var images = item.images || [];

            var html = '<article class="mom-card" data-post-id="' + item.id + '">'
                + '<header class="mom-card-head">'
                + (avatar
                    ? '<img class="mom-avatar" src="' + escapeHtml(avatar) + '" alt="' + escapeHtml(name) + '">'
                    : '<span class="mom-avatar">' + escapeHtml(String(name).charAt(0)) + '</span>')
                + '<div class="mom-card-meta"><span class="mom-name">' + escapeHtml(name) + '</span>'
                + (item.title ? '<h3 class="mom-title">' + escapeHtml(item.title) + '</h3>' : '')
                + '</div></header>'
                + '<div class="mom-card-body">'
                + (item.content ? '<p class="mom-text">' + escapeHtml(item.content) + '</p>' : '');

            if (images.length) {
                html += '<div class="mom-grid mom-grid-' + Math.min(images.length, 9)
                    + '" data-count="' + images.length + '">';
                for (var i = 0; i < images.length; i++) {
                    html += '<div class="mom-grid-cell"><img src="' + escapeHtml(images[i])
                        + '" alt="" loading="lazy" data-full="' + escapeHtml(images[i]) + '"></div>';
                }
                html += '</div>';
            }

            html += '<div class="mom-meta"><time class="mom-time" data-time="' + escapeHtml(item.create_time)
                + '">' + escapeHtml(item.create_time) + '</time>'
                + (item.is_admin ? '<button type="button" class="mom-post-delete" title="删除动态">删除</button>' : '')
                + '</div></div>'
                // 点赞 + 评论区
                + '<div class="mom-praise"><div class="mom-like-row' + (item.liked ? ' liked' : '') + '">'
                + '<i class="mom-like-icon fa fa-heart-o"></i>'
                + (item.likers && item.likers.length
                    ? '<span class="mom-like-nick">' + escapeHtml(item.likers.join('、')) + '</span>'
                    : '')
                + (item.likes_count > 0
                    ? '<span class="mom-like-count">(<span class="like-num">' + item.likes_count + '</span>)</span>'
                    : '')
                + '</div><div class="mom-comment-list" data-post-id="' + item.id + '"></div>'
                + '</div>'
                // 操作按钮
                + '<footer class="mom-card-foot">'
                + '<button type="button" class="mom-op mom-comment-open"><i class="fa fa-comment-o"></i> 评论'
                + (item.comments_count > 0 ? ' <span class="mom-op-num">' + item.comments_count + '</span>' : '')
                + '</button>'
                + '<button type="button" class="mom-op mom-more" title="赞 / 更多"><i class="fa fa-ellipsis-h"></i></button>'
                + '</footer>'
                // 赞 / 评论 操作浮层
                + '<div class="mom-pop" hidden">'
                + '<a href="javascript:;" class="mom-pop-item mom-pop-like"><i class="fa fa-heart"></i> 赞</a>'
                + '<a href="javascript:;" class="mom-pop-item mom-pop-comment"><i class="fa fa-comment-o"></i> 评论</a>'
                + '<i class="mom-pop-arrow"></i></div>'
                + '</article>';

            return html;
        }

        function showFeedEnd() {
            if (feedState.everMore) {
                $('.mom-feed-end').removeAttr('hidden');
            }
        }

        function checkFeedScroll() {
            if (feedState.loading || !feedState.hasMore) { return; }
            // 距底部 300px 触发预加载
            if ($(window).scrollTop() + $(window).height() >= $(document).height() - 300) {
                loadMoreFeed();
            }
        }

        function loadMoreFeed() {
            feedState.loading = true;
            $('.mom-feed-loading').removeAttr('hidden');

            request(NR_BASE + '/', 'GET', { page: feedState.page + 1 })
                .then(function (data) {
                    var items = (data && data.items) || [];
                    var $feed = $('.mom-feed');
                    for (var i = 0; i < items.length; i++) {
                        $feed.append(renderCard(items[i]));
                    }
                    feedState.page = (data && data.page) || feedState.page + 1;
                    feedState.hasMore = !!(data && data.has_more);
                    // 新卡片后处理：时间格式化 / 长图标记 / 评论自动展开
                    formatTimes();
                    markLongImages();
                    $feed.children('.mom-card').each(function () {
                        loadComments($(this));
                    });
                    if (!feedState.hasMore) {
                        $(window).off('scroll', checkFeedScroll);
                        showFeedEnd();
                    }
                })
                .fail(function () {
                    // 加载失败：静默，下次滚动重试
                })
                .always(function () {
                    feedState.loading = false;
                    $('.mom-feed-loading').attr('hidden', '');
                    // 追加后仍不足一屏时继续加载
                    checkFeedScroll();
                });
        }

        function initFeedScroll() {
            feedState.page = (window.NR_FEED && window.NR_FEED.page) || 1;
            feedState.hasMore = !!(window.NR_FEED && window.NR_FEED.has_more);
            feedState.everMore = feedState.hasMore;

            if (!feedState.hasMore) { return; }
            $(window).on('scroll', checkFeedScroll);
            // 首屏内容不足一屏时立即加载下一页
            checkFeedScroll();
        }

        /* -------------------------------------------------------------------
         * 评论：懒加载 / 底部输入条发送
         * ------------------------------------------------------------------- */
        var commentTarget = null;
        // 回复目标（null=普通评论）：点击评论内容时设置 { id, nickname }
        var replyTarget = null;

        function renderComment($list, item) {
            // 昵称链接：优先网址（新窗口打开），其次邮箱（mailto），均未填时纯文本
            var nick = escapeHtml(item.nickname || '访客');
            if (item.website) {
                nick = '<a class="comment-nick-link" href="' + escapeHtml(item.website)
                    + '" target="_blank" rel="nofollow noopener">' + nick + '</a>';
            } else if (item.email) {
                nick = '<a class="comment-nick-link" href="mailto:' + escapeHtml(item.email) + '">' + nick + '</a>';
            }
            // 博主昵称高亮（.mom-name 同款颜色，朋友圈式博主标识）
            var nickClass = 'comment-nick' + (item.is_owner ? ' comment-nick-owner' : '');
            // 回复目标（朋友圈式平铺：A 回复 B：内容），被回复昵称与 .mom-name 同色
            var reply = '';
            if (item.reply_to_id && item.reply_to_nickname) {
                reply = '<span class="comment-reply-arrow"> 回复 </span>'
                    + '<span class="comment-reply-target">' + escapeHtml(item.reply_to_nickname) + '</span>';
            }
            var html = '<div class="comment-item' + (item.is_mine ? ' mine' : '') + '"'
                + (item.id ? ' data-comment-id="' + item.id + '"' : '')
                + ' data-nickname="' + escapeHtml(item.nickname || '访客') + '"'
                + '>'
                + '<span class="' + nickClass + '">' + nick + reply + '</span>：'
                + '<span class="comment-text clamp">' + escapeHtml(item.content) + '</span>'
                + '</div>';
            $list.append(html);

            // 内容超过两行：追加「展开」入口（渲染后测量实际高度）
            var $text = $list.children('.comment-item').last().find('.comment-text');
            if ($text.length && $text[0].scrollHeight > $text[0].clientHeight + 2) {
                $text.after('<button type="button" class="comment-expand">展开</button>');
            }
        }

        // 展开/收起被折叠的评论内容（默认两行）
        $(document).on('click', '.comment-expand', function () {
            var $btn = $(this);
            var $text = $btn.prev('.comment-text');
            var collapsed = $text.hasClass('clamp');
            $text.toggleClass('clamp', !collapsed);
            $btn.text(collapsed ? '收起' : '展开');
        });

        function loadComments($card) {
            var postId = $card.attr('data-post-id');
            var $list = $card.find('.mom-comment-list');
            if ($list.data('loaded')) { return; }
            $list.html('<div class="comments-loading">加载中…</div>');

            request(NR_BASE + '/comments', 'GET', { post_id: postId })
                .then(function (data) {
                    $list.empty();
                    var items = (data && data.items) || [];
                    if (!items.length) { return; }
                    var hasLike = $card.find('.mom-like-nick').length > 0
                        || $card.find('.mom-like-row').hasClass('liked');
                    if (hasLike) { $card.find('.mom-like-row').addClass('has-divider'); }
                    $list.data('items', items);
                    renderCommentsLimited($list);
                })
                .fail(function () {
                    $list.html('<div class="comments-empty">评论加载失败，请稍后再试</div>');
                })
                .always(function () {
                    $list.data('loaded', true);
                });
        }

        /**
         * 限量渲染评论：默认展示前 NR_COMMENT_LIMIT 条（同后台「每页文章数」），
         * 超出部分折叠为「展开更多评论」按钮，点击后全部展开。
         */
        function renderCommentsLimited($list) {
            var items = $list.data('items') || [];
            var limit = parseInt(window.NR_COMMENT_LIMIT, 10) || 10;
            var shown = $list.data('expanded') ? items.length : Math.min(limit, items.length);

            $list.find('.comment-item, .comments-toggle').remove();
            for (var i = 0; i < shown; i++) {
                renderComment($list, items[i]);
            }
            if (items.length > shown) {
                $list.append('<button type="button" class="comments-toggle">展开更多评论（还有 '
                    + (items.length - shown) + ' 条）</button>');
            }
        }

        // 展开/收起被折叠的评论
        $(document).on('click', '.comments-toggle', function () {
            var $list = $(this).closest('.mom-comment-list');
            $list.data('expanded', !$list.data('expanded'));
            renderCommentsLimited($list);
        });

        function openCommentBar($card, reply) {
            commentTarget = $card;
            // 回复目标（null=普通评论）：由调用方传入，点击评论时为 { id, nickname }
            replyTarget = reply || null;
            var $bar = $('.mom-comment-bar');
            $bar.removeAttr('hidden');
            $bar.find('.mom-comment-input').val('')
                .attr('placeholder', reply ? '回复：' + reply.nickname : '评论');
            $bar.find('.mom-comment-send').prop('disabled', true);
            loadComments($card);
            setTimeout(function () { $bar.find('.mom-comment-input').trigger('focus'); }, 80);
        }

        // 点击评论内容/昵称发起回复（朋友圈式）：输入条提示「回复：xxx」，再次点击其他评论切换目标
        $(document).on('click', '.comment-item', function (e) {
            // 展开/收起按钮、昵称链接的点击不触发回复
            if ($(e.target).closest('.comment-expand, .comment-nick-link').length) { return; }
            var $item = $(this);
            var $card = $item.closest('.mom-card');
            openCommentBar($card, {
                id: parseInt($item.attr('data-comment-id'), 10) || 0,
                nickname: $item.attr('data-nickname') || '访客'
            });
        });

        // 评论按钮 / 浮层「评论」→ 打开输入条（普通评论，不带回复目标）
        $(document).on('click', '.mom-comment-open, .mom-pop-comment', function () {
            var $card = $(this).closest('.mom-card');
            hidePop($card.find('> .mom-pop'));
            openCommentBar($card);
        });

        // 点击评论区空白也可唤起
        $(document).on('click', '.mom-praise', function (e) {
            if ($(e.target).closest('.comment-item, .comments-toggle, .mom-more').length) { return; }
            openCommentBar($(this).closest('.mom-card'));
        });

        // 输入可用 → 启用发送
        $(document).on('input', '.mom-comment-input', function () {
            $('.mom-comment-send').prop('disabled', !$.trim($(this).val()));
        });

        // 发送评论
        function sendComment() {
            if (!commentTarget) { return; }
            var $card = commentTarget;
            var $bar = $('.mom-comment-bar');
            var content = $.trim($bar.find('.mom-comment-input').val() || '');
            if (!content) { return; }
            var nickname = $.trim($bar.find('.mom-comment-nick').val() || '');
            if (!nickname) { alert('请填写昵称'); return; }
            var email = $.trim($bar.find('.mom-comment-email').val() || '');
            var website = $.trim($bar.find('.mom-comment-website').val() || '');

            // 记忆昵称/邮箱/网址，下次访问自动填充；
            // 博主身份（昵称为自动填充的博客名称）不写入访客记忆，避免密码过期后残留
            var owner = $.trim(window.NR_OWNER_NICKNAME || '');
            var meta = readCommentMeta();
            if (!owner) { meta.nickname = nickname; }
            if (email) { meta.email = email; }
            if (website) { meta.website = website; }
            saveCommentMeta(meta);

            var $send = $bar.find('.mom-comment-send');
            $send.prop('disabled', true);

            request(NR_BASE + '/comments', 'POST', {
                post_id: $card.attr('data-post-id'),
                nickname: nickname,
                email: email,
                website: website,
                content: content,
                reply_to_id: replyTarget ? replyTarget.id : 0
            }).done(function (item) {
                var $list = $card.find('.mom-comment-list');
                $list.find('.comments-empty, .comments-loading').remove();
                renderComment($list, item || {});
                $list.data('loaded', true);
                $bar.find('.mom-comment-input').val('').attr('placeholder', '评论');
                $bar.find('.mom-comment-send').prop('disabled', true);
                // 刷新评论计数
                var $num = $card.find('.mom-op-num:not(.like-num)');
                if ($num.length) {
                    $num.text((parseInt($num.text(), 10) || 0) + 1);
                } else {
                    $card.find('.mom-comment-open').append('<span class="mom-op-num">1</span>');
                }
                closeCommentBar();
            }).fail(function (xhr) {
                alert((xhr.responseJSON && (xhr.responseJSON.msg || xhr.responseJSON.message)) || '发送失败，请稍后再试');
            }).always(function () {
                $send.prop('disabled', false);
            });
        }

        $(document).on('click', '.mom-comment-send', sendComment);
        $(document).on('keydown', '.mom-comment-input', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); sendComment(); }
        });

        function closeCommentBar() {
            commentTarget = null;
            replyTarget = null;
            $('.mom-comment-bar').attr('hidden', '');
        }

        // 评论层右上角关闭按钮 → 收起输入条
        $(document).on('click', '.mom-comment-close', function (e) {
            e.stopPropagation();
            closeCommentBar();
        });

        // 点击关闭按钮 / 外部 → 关闭输入条
        // iOS 键盘收起：无显式关闭，发布成功后自动收起
        // 表情面板豁免：移动端点 emoji 时 touchstart 先于 click 触发，若不豁免会先关掉输入条
        $(document).on('touchstart', function (e) {
            if (commentTarget && !$(e.target).closest('.mom-comment-bar').length
                && !$(e.target).closest('.mom-comment-emoji-panel').length
                && !$(e.target).closest('.mom-comment-open, .mom-pop-comment, .mom-praise').length) {
                closeCommentBar();
            }
        });

        /* -------------------------------------------------------------------
         * 写动态页：图片选择 / 上传 / 删除 / 发表
         * ------------------------------------------------------------------- */
        if ($('body[data-page="writer"]').length) {
            initWriter();
        }

        function initWriter() {
            var $textarea = $('.mom-writer-textarea');
            var $imagesWrap = $('#momWriterImages');
            var $count = $('#momWriteCount');
            var $postBtn = $('.mom-nav-post');
            var selectedUrls = [];
            var uploading = 0;

            // 隐藏 file input
            var $file = $('<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>');
            $('body').append($file);

            function reapply() {
                // 发送按钮状态：有内容或有图
                var hasContent = $.trim($textarea.val() || '').length > 0;
                $postBtn.prop('disabled', !(hasContent || selectedUrls.length) || uploading > 0);
                // 字数
                $count.text(($textarea.val() || '').length);
                renderThumbs();
            }

            function renderThumbs() {
                $imagesWrap.empty();
                var total = selectedUrls.length;
                for (var i = 0; i < total; i++) {
                    (function (url, idx) {
                        var $wrap = $('<div class="mom-writer-imgwrap">').append(
                            $('<img>').attr('src', url).attr('alt', '')
                        );
                        $wrap.append($('<button type="button" class="mom-writer-del" aria-label="删除"><i class="fa fa-close"></i></button>'));
                        $wrap.find('.mom-writer-del').on('click', function () {
                            selectedUrls.splice(idx, 1);
                            reapply();
                        });
                        $imagesWrap.append($wrap);
                    })(selectedUrls[i], i);
                }
                if (total < 9) {
                    $imagesWrap.append(
                        $('<div class="mom-writer-add" title="添加图片"><i class="fa fa-plus"></i></div>').on('click', function () {
                            $file.trigger('click');
                        })
                    );
                }
            }

            $textarea.on('input', reapply);
            $file.on('change', function () {
                var files = Array.prototype.slice.call($file[0].files || []);
                $file.val('');
                var remain = 9 - selectedUrls.length;
                files.slice(0, Math.max(remain, 0)).forEach(uploadImage);
            });

            function uploadImage(file) {
                uploading++;
                reapply();
                var fd = new FormData();
                fd.append('image', file);
                $.ajax({
                    url: window.NR_UPLOAD_URL,
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    headers: {
                        'X-Visitor-Id': getVisitorId(),
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''
                    }
                }).done(function (res) {
                    var data = unwrap(res);
                    if (data.url) { selectedUrls.push(data.url); }
                }).fail(function (xhr) {
                    alert((xhr.responseJSON && (xhr.responseJSON.msg || xhr.responseJSON.message)) || '图片上传失败');
                }).always(function () {
                    uploading--;
                    reapply();
                });
            }

            $postBtn.on('click', function () {
                if ($postBtn.prop('disabled')) { return; }
                $postBtn.prop('disabled', true).text('发表中…');
                request(window.NR_PUBLISH_URL, 'POST', {
                    content: $textarea.val() || '',
                    images: selectedUrls
                }).done(function () {
                    window.location.href = window.NR_LIST_URL;
                }).fail(function (xhr) {
                    $postBtn.prop('disabled', false).text('发表');
                    alert((xhr.responseJSON && (xhr.responseJSON.msg || xhr.responseJSON.message)) || '发布失败，请稍后再试');
                });
            });

            reapply();
        }
    }

    /* -------------------------------------------------------------------
     * 启动入口：jQuery 交由 layui 注入，无 layui 环境回退全局 jQuery
     * ------------------------------------------------------------------- */
    if (window.layui && typeof window.layui.use === 'function') {
        window.layui.use(['jquery'], function () {
            boot(window.layui.jquery);
        });
    } else if (window.jQuery) {
        boot(window.jQuery);
    }

})(window);