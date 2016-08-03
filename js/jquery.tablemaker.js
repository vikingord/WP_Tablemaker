jQuery(function ($) {

	// Iterates over all slideshows and starts them with the given settings
	// provided in the data attributes of the markup.
	// Also, for performance, dynamically loads images into placeholders.
	// This script should be loaded / run in the footer
	if ($('.hp-slideshow').length) {

		var lastResize = 0;

		var slideShowCallback = function (el, prev, next, duration) {
			resizeSlide(el);
		}

		jQuery.fn.reverse = [].reverse;

		$('.hp-slideshow').each(
			function () {
				var unloaded_count = $(this).find('span.hp-image-placeholder').length;
				var container = $(this);
				container.data('unloaded', unloaded_count);

				// Wait until first slide is completely loaded before loading rest.
				$(this).find('.hp-fade-gal > li:first-child img').each(
					function () {
						var first = $(this);
						var src = $(this).attr('src');
						$('<img/>').attr('src', src).load(
							function () {
								var w = this.width;
								var h = this.height;
								first.attr('data-p', (w / h))
									.closest('li').addClass('active')
									.css({visibility: 'visible', position: 'absolute'});
								first.fadeIn('fast', function () {
									loadSlides(container);
									resizeSlide(container);
								});
							}
						);
					}
				);
			}
		);

		function loadSlides(container) {
			var loaded_count = 0;
			container.find('.hp-fade-gal > li:not(:first-child)').each(
				function () {
					var src = $(this).attr('data-slide-src');
					$(this).find('span.hp-image-placeholder').replaceWith('<img src="' + src + '" />');
					$('<img/>').attr('src', src).load(
						function () {
							var w = this.width;
							var h = this.height;
							container.find('img[src="' + src + '"]').attr('data-p', (w / h))
								.closest('li').css('visibility', 'visible');
							loaded_count++;
							startIfReady(container, loaded_count);
						}
					);
				}
			);
		}

		// Checks if the given slideshow has loaded enough slides to start running
		function startIfReady(container, loaded_count) {
			container.data('loaded', loaded_count);
			var running = container.data('running');

			if (container.data('unloaded') <= loaded_count || loaded_count > 3) {
				if (running) {
					return;
				}

				container.find('.hp-fade-gal li:not(:first-child) img').css('display', 'inline-block');
				container.data('running', true);

				var delay = parseInt(container.attr('data-delay'));
				var auto = container.attr('data-auto');
				var duration = parseInt(container.attr('data-duration'));
				if (isNaN(delay)) {
					delay = 3000;
				}
				if (isNaN(duration)) {
					duration = 750;
				}

				if (typeof auto != 'undefined') {
					auto = (auto && auto != '0') ? true : false;
				} else {
					auto = true;
				}

				var pager_links = container.attr('pager');
				if (typeof pager_links == 'undefined') {
					pager_links = 'ul.hp-control li.hp-slide-page a';
				}

				var page_event = container.attr('hover');
				if (typeof page_event != 'undefined') {
					page_event = (page_event && page_event != '0') ? 'hover' : 'click';
				} else if (page_event == 'true') {
					page_event = 'click';
				}

				container.fadeGallery({
					slideElements: 'ul.hp-fade-gal > li',
					pagerLinks: pager_links,
					switchTime: delay,
					duration: duration,
					autoRotation: auto,
					event: page_event,
					callback: function (el, prev, next, duration) {
						if (typeof(slideShowCallback) === 'function') {
							slideShowCallback(el, prev, next, duration);
						}
					}
				});
			}
		}

		// Set up the thumbs
		$(window).load(function () {
			$('.hp-control-wrapper').each(
				function () {
					var container = $(this);
					var mask = container.find('.hp-control-mask');
					var ul = container.find('ul');

					ul.find('li').css({display: 'inline-block'});
					ul.find('a').css({display: 'block'});

					var w = 0;
					var mh = 0;
					container.find('li').each(
						function () {
							w += $(this).outerWidth(true) + 2;
							mh = Math.max(mh, $(this).outerHeight(true));
						}
					);

					mask.css({position: 'relative', overflow: 'hidden', height: mh, width: 'auto'});

					if (w > container.outerWidth(true)) {
						container.append('<a href="javascript:void(0);" class="hp-control-nav hp-control-nav-prev">&larr;</a>');
						container.append('<a href="javascript:void(0);" class="hp-control-nav hp-control-nav-next">&rarr;</a>');
						ul.css({
							display: 'block',
							maxWidth: 'none',
							position: 'absolute',
							top: 0,
							left: 0,
							width: w,
							height: mh
						});

						var prev = container.find('.hp-control-nav-prev');
						var next = container.find('.hp-control-nav-next');

						prev.css({position: 'absolute', display: 'block', top: 0, left: 0, bottom: 0, width: 50});
						next.css({position: 'absolute', display: 'block', top: 0, right: 0, bottom: 0, width: 50});

						mask.css({marginLeft: prev.outerWidth(), marginRight: next.outerWidth()});

						$('.hp-control-nav').click(
							function () {
								slideHPNav($(this));
							}
						);
					}
				}
			);
		});
	}

	function slideHPNav(el) {
		var container = $(el).closest('.hp-control-wrapper');
		var dir = ($(el).hasClass('hp-control-nav-prev')) ? 1 : -1;
		var ul = container.find('ul');
		var mask = container.find('.hp-control-mask');
		var prev = container.find('.hp-control-nav-prev');
		var next = container.find('.hp-control-nav-next');
		var cur = parseInt(ul.css('left').replace('px', ''));
		var w = parseInt(ul.css('width').replace('px', ''));
		var inc = mask.outerWidth();
		var moveto = cur + (inc * dir);
		var hideprev = false;
		var hidenext = false;

		moveto = Math.min(0, moveto);

		if ((moveto + w) < inc) {
			moveto = inc - w;
			hidenext = true;
		}

		if (moveto == 0) {
			hideprev = true;
		}

		if (hidenext) {
			next.fadeOut();
		} else {
			next.fadeIn();
		}

		if (hideprev) {
			prev.fadeOut();
		} else {
			prev.fadeIn();
		}

		ul.animate({left: moveto}, 400);

	}

	function resizeSlide(el) {
		if ( ! el || el.hasClass('hp-slideshow')) {
			el = '.hp-slideshow ul.hp-fade-gal';
		}

		var container = $(el);
		// Switched from container width to img width to try and prevent issues when image isn't full width of slide
		// var cw = container.width();
		var img = container.find('li.active img');
		var cw = img.width();
		var p = img.attr('data-p');
		if (typeof p == 'undefined') {
			$('<img/>').attr('src', img.attr('src')).load(
				function () {
					var w = this.width;
					var h = this.height;
					p = w / h;
					container.css({height: (cw / p)});
				}
			);
		} else {
			container.css({height: (cw / p)});
		}
	}

	$(window).resize(
		function () {
			if (Math.abs(lastResize - $(window).width()) > 3) {
				lastResize = $(window).width();
				resizeSlide($('.hp-slideshow'));
			}
		}
	);
});

// slideshow plugin
jQuery.fn.fadeGallery = function (_options) {
	var _options = jQuery.extend({
		slideElements: 'ul.hp-fade-gal > li',
		pagerLinks: 'ul.hp-control a',
		pagerHover: false,
		btnNext: 'a.hp-next',
		btnPrev: 'a.hp-prev',
		btnPlayPause: 'a.play-pause',
		btnPlay: 'a.hp-play',
		btnPause: 'a.pause',
		pausedClass: 'paused',
		disabledClass: 'disabled',
		playClass: 'playing',
		activeClass: 'active',
		currentNum: false,
		allNum: false,
		startSlide: null,
		noCircle: false,
		pauseOnHover: true,
		autoRotation: true,
		autoHeight: false,
		onChange: false,
		switchTime: 4000,
		duration: 2000,
		event: 'click',
		callback: ''
	}, _options);

	return this.each(function () {
		// gallery options
		var _this = jQuery(this);
		var _slides = jQuery(_options.slideElements, _this);
		var _pagerLinks = jQuery(_options.pagerLinks, _this);
		if ( ! _pagerLinks.length) {
			_pagerLinks = jQuery(_options.pagerLinks);
		}
		var _btnPrev = jQuery(_options.btnPrev, _this);
		var _btnNext = jQuery(_options.btnNext, _this);
		var _btnPlayPause = jQuery(_options.btnPlayPause, _this);
		var _btnPause = jQuery(_options.btnPause, _this);
		var _btnPlay = jQuery(_options.btnPlay, _this);
		var _pauseOnHover = _options.pauseOnHover;
		var _autoRotation = _options.autoRotation;
		var _activeClass = _options.activeClass;
		var _disabledClass = _options.disabledClass;
		var _pausedClass = _options.pausedClass;
		var _playClass = _options.playClass;
		var _autoHeight = _options.autoHeight;
		var _duration = _options.duration;
		var _switchTime = _options.switchTime;
		var _controlEvent = _options.event;
		var _currentNum = (_options.currentNum ? jQuery(_options.currentNum, _this) : false);
		var _allNum = (_options.allNum ? jQuery(_options.allNum, _this) : false);
		var _startSlide = _options.startSlide;
		var _noCycle = _options.noCircle;
		var _onChange = _options.onChange;
		var callback = _options.callback;

		// gallery init
		var _hover = false;
		var _prevIndex = 0;
		var _currentIndex = 0;
		var _slideCount = _slides.length;
		var _timer;
		if (_slideCount < 2) return;

		_prevIndex = _slides.index(_slides.filter('.' + _activeClass));
		if (_prevIndex < 0) _prevIndex = _currentIndex = 0;
		else _currentIndex = _prevIndex;
		if (_startSlide != null) {
			if (_startSlide == 'random') _prevIndex = _currentIndex = Math.floor(Math.random() * _slideCount);
			else _prevIndex = _currentIndex = parseInt(_startSlide);
		}
		_slides.hide().eq(_currentIndex).show();
		if (_autoRotation) _this.removeClass(_pausedClass).addClass(_playClass);
		else _this.removeClass(_playClass).addClass(_pausedClass);

		// gallery control
		if (_btnPrev.length) {
			_btnPrev.bind(_controlEvent, function () {
				prevSlide();
				return false;
			});
		}

		if (_btnNext.length) {
			_btnNext.bind(_controlEvent, function () {
				nextSlide();
				return false;
			});
		}

		if (_pagerLinks.length) {
			_pagerLinks.each(function (_ind) {
				jQuery(this).bind(_controlEvent, function () {
					if (_currentIndex != _ind) {
						_prevIndex = _currentIndex;
						_currentIndex = _ind;
						switchSlide();
					}
					return false;
				});
			});
		}

		// play pause section
		if (_btnPlayPause.length) {
			_btnPlayPause.bind(_controlEvent, function () {
				if (_this.hasClass(_pausedClass)) {
					_this.removeClass(_pausedClass).addClass(_playClass);
					_autoRotation = true;
					autoSlide();
				} else {
					_autoRotation = false;
					if (_timer) clearTimeout(_timer);
					_this.removeClass(_playClass).addClass(_pausedClass);
				}
				return false;
			});
		}
		if (_btnPlay.length) {
			_btnPlay.bind(_controlEvent, function () {
				_this.removeClass(_pausedClass).addClass(_playClass);
				_autoRotation = true;
				autoSlide();
				return false;
			});
		}
		if (_btnPause.length) {
			_btnPause.bind(_controlEvent, function () {
				_autoRotation = false;
				if (_timer) clearTimeout(_timer);
				_this.removeClass(_playClass).addClass(_pausedClass);
				return false;
			});
		}

		// gallery animation
		function prevSlide() {
			_prevIndex = _currentIndex;
			if (_currentIndex > 0) _currentIndex--;
			else {
				if (_noCycle) return;
				else _currentIndex = _slideCount - 1;
			}
			switchSlide();
		}

		function nextSlide() {
			_prevIndex = _currentIndex;
			if (_currentIndex < _slideCount - 1) _currentIndex++;
			else {
				if (_noCycle) return;
				else _currentIndex = 0;
			}
			switchSlide();
		}

		function refreshStatus() {
			if (_pagerLinks.length) _pagerLinks.removeClass(_activeClass).eq(_currentIndex).addClass(_activeClass);
			if (_currentNum) _currentNum.text(_currentIndex + 1);
			if (_allNum) _allNum.text(_slideCount);
			_slides.eq(_prevIndex).removeClass(_activeClass);
			_slides.eq(_currentIndex).addClass(_activeClass);
			if (_noCycle) {
				if (_btnPrev.length) {
					if (_currentIndex == 0) _btnPrev.addClass(_disabledClass);
					else _btnPrev.removeClass(_disabledClass);
				}
				if (_btnNext.length) {
					if (_currentIndex == _slideCount - 1) _btnNext.addClass(_disabledClass);
					else _btnNext.removeClass(_disabledClass);
				}
			}
			if (typeof _onChange === 'function') {
				_onChange(_this, _currentIndex);
			}
		}

		function switchSlide() {
			if (callback) {
				callback(_this, _slides.eq(_prevIndex), _slides.eq(_currentIndex), _duration);
			}
			_slides.eq(_prevIndex).fadeOut(_duration);
			_slides.eq(_currentIndex).fadeIn(_duration);
			if (_autoHeight) _slides.eq(_currentIndex).parent().animate({height: _slides.eq(_currentIndex).outerHeight(true)}, {
				duration: _duration,
				queue: false
			});
			refreshStatus();
			autoSlide();
		}

		// autoslide function
		function autoSlide() {
			if (!_autoRotation || _hover) return;
			if (_timer) clearTimeout(_timer);
			_timer = setTimeout(nextSlide, _switchTime + _duration);
		}

		if (_pauseOnHover) {
			_this.hover(function () {
				_hover = true;
				if (_timer) clearTimeout(_timer);
			}, function () {
				_hover = false;
				autoSlide();
			});
		}
		refreshStatus();
		autoSlide();
	});
}