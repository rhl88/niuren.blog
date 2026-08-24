/**
 * 朋友圈博客 前台交互脚本（v1.3.0）
 * 依赖：jQuery（页面已引入 layui 自带 jQuery）
 * 职责：
 *  1. 注入 X-Visitor-Id / X-CSRF-TOKEN 请求头（访客身份三级策略）
 *  2. 顶部导航滚动渐变 + 封面视差 + 深浅色主题切换
 *  3. 点赞（···浮层/卡片）乐观更新 + 回滚
 *  4. 评论区懒加载 + 底部评论输入条发送
 *  5. 九宫格长图标注 + 全屏图片浏览
 *  6. 写动态页：图片选择/上传/删除/发表
 */
(function (window) {
    'use strict';

    function boot($) {

        var VISITOR_KEY = 'nr_visitor_id';
        var COOKIE_NAME = 'nr_visitor';
        var THEME_KEY = 'nr_theme';

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
         * 主题切换 + 导航滚动 + 封面视差
         * ------------------------------------------------------------------- */
        function initTheme() {
            var saved = '';
            try { saved = window.localStorage.getItem(THEME_KEY) || ''; } catch (e) { /* ignore */ }
            if (saved) {
                document.documentElement.setAttribute('data-theme', saved);
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.setAttribute('data-theme', 'dark');
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
            var next = isDark ? '' : 'dark';
            if (next) {
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
            var $grid = $(this).closest('.mom-grid');
            var urls = [];
            $grid.find('.mom-grid-cell img').each(function () { urls.push($(this).attr('data-full') || this.src); });
            var idx = urls.indexOf(this.src);
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
        function setLiked(postId, liked, count) {
            var $card = $('.mom-card[data-post-id="' + postId + '"]');
            $card.find('.mom-like-row').toggleClass('liked', liked);
            var $num = $card.find('.like-num');
            if (!$num.length) {
                var $row = $card.find('.mom-like-row');
                if (count > 0) {
                    $row.append('<span class="mom-like-count">(<span class="like-num">' + count + '</span>)</span>');
                }
            } else {
                $num.text(count);
                if (count <= 0) { $num.closest('.mom-like-count').remove(); }
            }
        }

        function toggleLike(postId, $btn) {
            var $card = $('.mom-card[data-post-id="' + postId + '"]');
            var wasLiked = $card.find('.mom-like-row').hasClass('liked');
            if (!wasLiked) { setLiked(postId, true, (parseInt($card.find('.like-num').text(), 10) || 0) + 1); }

            request('/blog/like', 'POST', { post_id: postId })
                .done(function (data) {
                    setLiked(postId, !!data.liked, typeof data.count !== 'undefined' ? data.count : null);
                })
                .fail(function (xhr) {
                    // 回滚
                    var cnt = parseInt($card.find('.like-num').text(), 10) || 0;
                    setLiked(postId, wasLiked, wasLiked ? cnt + 1 : Math.max(cnt - 1, 0));
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

        // 点击浮层/触发按钮之外的区域收起
        $(document).on('click', function (e) {
            if ($(e.target).closest('.mom-pop, .mom-more').length) { return; }
            hidePop();
        });

        /* -------------------------------------------------------------------
         * 评论：懒加载 / 底部输入条发送
         * ------------------------------------------------------------------- */
        var commentTarget = null;

        function renderComment($list, item) {
            var html = '<div class="comment-item' + (item.is_mine ? ' mine' : '') + '"'
                + (item.id ? ' data-comment-id="' + item.id + '"' : '')
                + '>'
                + '<span class="comment-nick">' + escapeHtml(item.nickname || '访客') + '</span>：'
                + '<span class="comment-text">' + escapeHtml(item.content) + '</span>'
                + '</div>';
            $list.append(html);
        }

        function loadComments($card) {
            var postId = $card.attr('data-post-id');
            var $list = $card.find('.mom-comment-list');
            if ($list.data('loaded')) { return; }
            $list.html('<div class="comments-loading">加载中…</div>');

            request('/blog/comments', 'GET', { post_id: postId })
                .then(function (data) {
                    $list.empty();
                    var items = (data && data.items) || [];
                    if (!items.length) { return; }
                    var hasLike = $card.find('.mom-like-row').find('.like-num').length > 0
                        || $card.find('.mom-like-row').hasClass('liked');
                    if (hasLike) { $card.find('.mom-like-row').addClass('has-divider'); }
                    for (var i = 0; i < items.length; i++) {
                        renderComment($list, items[i]);
                    }
                })
                .fail(function () {
                    $list.html('<div class="comments-empty">评论加载失败，请稍后再试</div>');
                })
                .always(function () {
                    $list.data('loaded', true);
                });
        }

        function openCommentBar($card) {
            commentTarget = $card;
            var $bar = $('.mom-comment-bar');
            $bar.removeAttr('hidden');
            $bar.find('.mom-comment-input').val('');
            $bar.find('.mom-comment-send').prop('disabled', true);
            loadComments($card);
            setTimeout(function () { $bar.find('.mom-comment-input').trigger('focus'); }, 80);
        }

        // 评论按钮 / 浮层「评论」→ 打开输入条
        $(document).on('click', '.mom-comment-open, .mom-pop-comment', function () {
            var $card = $(this).closest('.mom-card');
            hidePop($card.find('> .mom-pop'));
            openCommentBar($card);
        });

        // 点击评论区空白也可唤起
        $(document).on('click', '.mom-praise', function (e) {
            if ($(e.target).closest('.comment-item, .mom-more').length) { return; }
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
            var nickname = $.trim($bar.find('.mom-comment-nick').val() || '') || '访客';

            var $send = $bar.find('.mom-comment-send');
            $send.prop('disabled', true);

            request('/blog/comments', 'POST', {
                post_id: $card.attr('data-post-id'),
                nickname: nickname,
                content: content
            }).done(function (item) {
                var $list = $card.find('.mom-comment-list');
                $list.find('.comments-empty, .comments-loading').remove();
                renderComment($list, item || {});
                $list.data('loaded', true);
                $bar.find('.mom-comment-input').val('');
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
            $('.mom-comment-bar').attr('hidden', '');
        }

        // 点击关闭按钮 / 外部 → 关闭输入条
        // iOS 键盘收起：无显式关闭，发布成功后自动收起
        $(document).on('touchstart', function (e) {
            if (commentTarget && !$(e.target).closest('.mom-comment-bar').length
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
            var $postBtn = $('.mom-writer-post');
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