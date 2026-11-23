export const tools = {
	filters: {

	},
	computed: {

	},
	methods: {
		//富文本的回调
		navigate(e) {
			if (e.href && e.href.indexOf('http') == -1) { //不完整的链接						
				//详情				
				let res = e.href.match(new RegExp("(a)|(\\d+)", 'g'));
				if (res.length == 2) {
					this.$u.route('/pages/article/detail', {
						id: res[1]
					});
					return;
				}
				// #ifdef MP
				this.$util.uniCopy({
					content: this.vuex_config.upload.cdnurl + e.href,
					success: () => {
						this.$u.toast('链接已复制,请在浏览器中打开')
					}
				})
				// #endif
				// #ifndef MP				
				window.open(this.vuex_config.upload.cdnurl + e.href);
				// #endif
			}
		},
		//预览图片
		lookImage(index) {
			uni.previewImage({
				current: index,
				urls: this.imagesList,
				longPressActions: {
					itemList: ['发送给朋友', '保存图片', '收藏'],
					success: function(data) {
						console.log(data)
					},
					fail: function(err) {
						console.log(err.errMsg);
					}
				}
			});
		},
		//复制url
		copyUrl(url = '') {
			this.$util.uniCopy({
				content: url || window.location.href,
				success: () => {
					this.$u.toast('复制成功，请去粘贴发送给好友吧');
				},
				error: () => {
					console.log('复制失败！')
				}
			})
		},
		//cdnurl
		cdnurl(url) {
			if (!/^((?:[a-z]+:)?\/\/|data:image\/)(.*)/.test(url)) {
				return this.vuex_config.upload.cdnurl + url;
			}
			return url;
		},
		//页面跳转
		goPage(path, auth) {
			if (path == 'out') {
				this.$u.vuex('vuex_token', '');
				this.$u.vuex('vuex_user', {});
				this.$u.vuex('vuex_openid', '');
				return;
			}
			if (auth && !this.vuex_token) {
				this.$u.route('/pages/login/mobilelogin');
				return;
			}
			let type = 'navigateTo';

			// #ifdef MP-WEIXIN
			//优化当pages超过5个时的处理
			let pages = getCurrentPages();
			type = pages.length >= 4 ? 'redirectTo' : type;
			// #endif

			uni.$u.route({
				url: path,
				type: type,
				complete(e) {
					console.log(e, path)
				}
			})
		}
	}
}


//form
export const formRule = {
	methods: {
		//表单验证
		getRules(row) {
			let arr = row.rule.replace(/;/, ',').split(',');
			let rule_arr = [];

			arr.forEach(item => {
				item = this.$u.trim(item);
				switch (item) {
					case 'required':
					case 'checked':
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (typeof value == 'string') {
									value = value.replace(/<[^>]+>/g, "").replace(/\s/ig, "");
								}
								return !(this.$u.test.empty(value));
							},
							required: true,
							message: row.title + '不能为空',
							// 可以单个或者同时写两个触发验证方式
							trigger: ['change', 'blur']
						});
						break;
					case 'digits': //数字校验
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return this.$u.test.digits(value);
							},
							message: '请填写数字',
							trigger: ['change', 'blur']
						});
						break;
					case 'letters': //字母校验
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return this.$u.test.letter(value);
							},
							message: '请填写字母',
							trigger: ['change', 'blur']
						});
						break;
					case 'date': //日期校验
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return this.$u.test.date(value);
							},
							message: '请填写正确日期格式',
							trigger: ['change', 'blur']
						});
						break;
					case 'time': //时间校验
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return /^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/.test(value);
							},
							message: '请填写正确时间格式',
							trigger: ['change', 'blur']
						});
						break;
					case 'email': //邮箱校验
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return this.$u.test.email(value);
							},
							message: '请填写正确邮箱',
							trigger: ['change', 'blur']
						});
						break;
					case 'url': //网址
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return this.$u.test.url(value);
							},
							message: '请填写正确网址',
							trigger: ['change', 'blur']
						});
						break;
					case 'qq': //qq
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return /^[1-9][0-9]{4,10}$/.test(value);
							},
							message: '请填写正确QQ号码',
							trigger: ['change', 'blur']
						});
						break;
					case 'IDcard': //身份证
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return this.$u.test.idCard(value);
							},
							message: '请填写正确身份证件号',
							trigger: ['change', 'blur']
						});
						break;
					case 'tel': //电话
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return /^\d{3}-\d{8}$|^\d{4}-\d{7,8}$/.test(value);
							},
							message: '请填写正确电话号码',
							trigger: ['change', 'blur']
						});
						break;
					case 'mobile': //手机
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return this.$u.test.mobile(value);
							},
							message: '请填写正确手机号码',
							trigger: ['change', 'blur']
						});
						break;
					case 'zipcode': //邮编
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return /^(0[1-7]|1[0-356]|2[0-7]|3[0-6]|4[0-7]|5[1-7]|6[1-7]|7[0-5]|8[013-6])\d{4}$/.test(value);
							},
							message: '请填写正确邮编',
							trigger: ['change', 'blur']
						});
						break;
					case 'chinese': //中文
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return /^(?:[\u3400-\u4DB5\u4E00-\u9FEA\uFA0E\uFA0F\uFA11\uFA13\uFA14\uFA1F\uFA21\uFA23\uFA24\uFA27-\uFA29]|[\uD840-\uD868\uD86A-\uD86C\uD86F-\uD872\uD874-\uD879][\uDC00-\uDFFF]|\uD869[\uDC00-\uDED6\uDF00-\uDFFF]|\uD86D[\uDC00-\uDF34\uDF40-\uDFFF]|\uD86E[\uDC00-\uDC1D\uDC20-\uDFFF]|\uD873[\uDC00-\uDEA1\uDEB0-\uDFFF]|\uD87A[\uDC00-\uDFE0])+$/.test(value);
							},
							message: '请填写中文',
							trigger: ['change', 'blur']
						});
						break;
					case 'username': //用户名
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								return /^[a-zA-Z0-9_]{3,12}$/.test(value);
							},
							message: '请填写3-12位数字、字母、下划线',
							trigger: ['change', 'blur']
						});

						break;
					case 'password': //密码
						rule_arr.push({
							validator: (rule, value, callback) => {
								if (!rule.required && this.$u.test.empty(value)) {
									callback();
								}
								let val = this.$u.trim(value, 'all');
								if (val != value) {
									return false;
								}
								return /^[0-9a-zA-Z!@#$%^&*?]{6,16}$/.test(value);
							},
							message: '请填写6-16位字符，不能包含空格',
							trigger: ['change', 'blur']
						});
						break;
				}
			});

			//多选额外的判断
			if (row.type == 'checkbox') {
				//最少
				if (row.minimum > 0) {
					rule_arr.push({
						validator: (rule, value, callback) => {
							if (!rule.required && this.$u.test.empty(value)) {
								callback();
							}
							let arr = value.split(',')
							return arr.length >= row.minimum;
						},
						required: true,
						message: '最少必须选择' + row.minimum + '项',
						// 可以单个或者同时写两个触发验证方式
						trigger: ['change', 'blur']
					});
				}
			}
			if (['checkbox', 'selects', 'images', 'files'].indexOf(row.type) != -1) {
				//最多
				if (row.maximum > 0) {
					rule_arr.push({
						validator: (rule, value, callback) => {
							if (!rule.required && this.$u.test.empty(value)) {
								callback();
							}
							let arr = value.split(',')
							return arr.length <= row.maximum;
						},
						message: '最多只能选择' + row.maximum + '项',
						// 可以单个或者同时写两个触发验证方式
						trigger: ['change', 'blur']
					});
				}
			}

			return rule_arr;
		}
	}
}

//点赞
export const vote = {
	methods: {
		likes: async function() {
			const value = uni.getStorageSync(`${this.id}_${this.vuex_user.id}`);
			if (value == this.id) {
				this.$u.toast('您已经点过赞了')
				return;
			}
			let res = await this.$api.getArchivesVote({
				id: this.id,
				type: 'like'
			})
			this.$u.toast(res.msg);
			if (!res.code) {
				return;
			};
			//先在前端限制
			uni.setStorageSync(`${this.id}_${this.vuex_user.id}`, this.id);
			this.$set(this.archivesInfo, 'likes', res.data.likes)
		},
		collection(id, type) {
			this.$api.addCollection({
				aid: id,
				type: type
			}).then(res => {
				this.$u.toast(res.msg)
			})
		}
	}
}

//修改头像的事件
export const avatar = {
	methods: {
		chooseAvatar() {
			uni.$on('uAvatarCropper', this.upload);
			this.$u.route({
				// 关于此路径，请见下方"注意事项"
				url: '/uview-ui/components/u-avatar-cropper/u-avatar-cropper',
				// 内部已设置以下默认参数值，可不传这些参数
				params: {
					// 输出图片宽度，高等于宽，单位px
					destWidth: 300,
					// 裁剪框宽度，高等于宽，单位px
					rectWidth: 300,
					// 输出的图片类型，如果'png'类型发现裁剪的图片太大，改成"jpg"即可
					fileType: 'jpg'
				}
			});
		},
		upload: async function(path) {
			uni.$off('uAvatarCropper', this.upload);
			// 可以在此上传到服务端
			try {
				let res = await this.$api.goUpload({
					filePath: path
				});
				if (!res.code) {
					this.$u.toast(res.msg);
				}
				this.form.avatar = res.data.url;
				this.url = res.data.fullurl;
				if (typeof this.editAvatar == 'function') {
					this.editAvatar();
				}
			} catch (e) {
				console.error(e);
				this.$u.toast('图片上传失败！');
			}
		}
	}
}

// 登录方法
export const loginfunc = {
	methods: {
		// 登录成功
		async success(delta) {
			//重置用户信息
			let apptype = '';
			let platform = '';
			let logincode = '';

			// #ifdef MP-WEIXIN
			platform = 'wechat';
			apptype = 'miniapp';
			logincode = await this.getMpCode();
			// #endif

			this.$api.getUserIndex({ apptype, platform, logincode }).then(res => {

				if (res.code) {
					this.$u.vuex('vuex_user', res.data.userInfo);
					if (res.data.openid) {
						this.$u.vuex('vuex_openid', res.data.openid);
					}
				}

				console.log(delta);
				var pages = getCurrentPages();
				if (!delta) {
					delta = 0;
					for (let i = pages.length; i > 0; i--) {
						console.log(pages[i - 1].route);
						if (pages[i - 1].route.indexOf("pages/login/") == -1) {
							break;
						} else {
							delta++;
						}
					}
					//根据pages自动计算出的delta
					console.log(delta);
				}

				let url = this.vuex_lasturl || '/pages/index/index';
				//清空最后页面
				this.$u.vuex('vuex_lasturl', '');

				//不在H5
				// #ifndef H5
				if (typeof pages[delta] !== 'undefined') {
					uni.navigateBack({
						delta: delta
					});
				} else {
					uni.reLaunch({
						url: url
					});
				}
				// #endif

				// 在H5 刷新导致路由丢失
				// #ifdef H5
				//有上次页面，关闭所有页面，到此页面,是从授权的，授权页面被刷新过
				if (pages.length <= 1 || pages[0].route.match(/pages\/login\//)) {
					uni.reLaunch({
						url: url
					});
				} else {
					uni.navigateBack({
						delta: delta
					});
				}
				// #endif

			});

		},
		// #ifdef MP-WEIXIN
		// 获取手机号回调
		async getPhoneNumber(e) {
			if (e.detail.errMsg === "privacy permission is not authorized") {
				this.$refs.uToast.show({
					title: '授权失败，失败原因：未同意隐私协议',
					type: 'error'
				});
				return;
			}
			if (e.detail.errMsg === "privacy permission is not authorized in gap") {
				this.$refs.uToast.show({
					title: '授权失败，原因：未同意隐私协议，请稍后重试',
					type: 'error'
				});
				return;
			}
			if (e.detail.errMsg === "getPhoneNumber:fail user deny") {
				this.$refs.uToast.show({
					title: '授权失败，原因：用户拒绝',
					type: 'error'
				});
				return;
			}
			if (e.detail.errMsg !== "getPhoneNumber:ok") {
				this.$refs.uToast.show({
					title: '授权失败，原因：' + e.detail.errMsg,
					type: 'error'
				});
				return;
			}
			if (typeof this.agreeChecked !== 'undefined' && !this.agreeChecked) {
				this.$refs.uToast.show({
					title: '请阅读并同意遵守《用户协议》',
					type: 'error'
				});
				return;
			}

			let logincode = await this.getMpCode();
			this.$api.goWechatMobileLogin({
				code: e.detail.code,
				logincode: logincode,
				bind: this.is_bind || ''
			}).then((res) => {
				if (res.code == 1) {
					this.$u.vuex('vuex_token', res.data.token);
					this.$u.vuex('vuex_openid', res.data.openid);
					this.success();
				} else {
					this.$u.toast(res.msg);
				}
			});

		},
		// 获取微信小程序用户openid
		async getWechatOpenid() {
			let logincode = await this.getMpCode();
			let res = await this.$api.getWechatOpenid({
				logincode: logincode,
			});
			if (res.code && res.data.openid) {
				this.$u.vuex('vuex_openid', res.data.openid);
				return res.data.openid;
			}
			return '';
		},
		// #endif

		// #ifdef H5
		// 公众号授权
		async goAuth(page, scope) {
			if (this.$util.isWeiXinBrowser()) {
				page = page ? page : '/pages/login/auth';

				let url = window.location.origin + (window.location.hash != '' ?
					window.location.pathname + '?hashpath=' + page :
					window.location.pathname.replace(/\/pages\/.*/, page));

				let res = await this.$api.getAuthUrl({
					platform: 'wechat',
					url: url,
					scope: scope || "",
				});
				if (!res.code) {
					this.$u.toast(res.msg);
					return;
				}
				var pages = getCurrentPages();
				let len = pages.length;
				if (len > 1) {
					let url = pages[len - 1].route;
					if (url.indexOf('/login/') != -1) {
						//找到上一个不是登录页面
						for (let i = len - 1; i >= 0; i--) {
							if (pages[i].route.indexOf('/login/') == -1) {
								this.$u.vuex('vuex_lasturl', '/' + pages[i].route + this.$u.queryParams(pages[i].options));
								break;
							}
						}
					} else {
						this.$u.vuex('vuex_lasturl', '/' + url + this.$u.queryParams(pages[pages.length - 1].options))
					}
				}
				window.location.href = res.data;
			}
		},
		// #endif
		// #ifdef MP-WEIXIN
		// 获取登录Code
		async getMpCode() {
			return new Promise((resolve, reject) => {
				uni.login({
					success: function(res) {
						if (res.code) {
							resolve(res.code);
						} else {
							//login成功，但是没有取到code
							reject('未取得code');
						}
					},
					fail: function(res) {
						reject('用户授权失败wx.login');
					}
				});
			});
		},
		// 微信授权登录
		async goMpLogin() {
			uni.showLoading({ title: '登录中...' });
			let that = this;
			try {
				let code = await that.getMpCode();
				let data = {
					code: code,
					__token__: that.vuex__token__
				};
				//有推荐码的话带上
				if (that.vuex_invitecode) {
					data.invitecode = that.vuex_invitecode;
				}
				let res = await this.$api.gowxLogin(data);
				uni.hideLoading();
				if (!res.code) {
					this.$u.toast(res.msg);
					return;
				}
				if (res.data.user) {
					this.$u.vuex('vuex_token', res.data.user.token);
					this.success();
					return;
				}
				this.$u.vuex('vuex_third', res.data.third);

				//授权成功到登录或绑定页面
				this.$u.route('/pages/login/register?bind=bind');
			} catch (e) {
				uni.hideLoading();
				that.$u.toast(e);
			}
		},
		// #endif
		// #ifdef APP-PLUS
		// App登录
		goAppLogin() {
			let that = this;
			var all, Service;
			// 1.发送请求获取code
			plus.oauth.getServices(
				function(Services) {
					all = Services;
					Object.keys(all).some(key => {
						if (all[key].id == 'weixin') {
							Service = all[key];
						}
					});
					Service.authorize(
						async function(e) {
								let res = await that.$api.goAppLogin({
									code: e.code,
									scope: e.scope
								});
								if (!res.code) {
									that.$u.toast(res.msg);
									return;
								}
								if (res.data.user) {
									that.$u.vuex('vuex_token', res.data.user.token);
									that.$u.vuex('vuex_user', res.data.user || {});
									that.success();
									return;
								}
								that.$u.vuex('vuex_third', res.data.third);
								that.$u.vuex('vuex_openid', res.data.openid);
								that.$u.route('/pages/login/register?bind=bind');
							},
							function(e) {
								that.$u.toast('授权失败！');
							}
					);
				},
				function(err) {
					console.log(err);
					that.$u.toast('授权失败！');
				}
			);
		},
		// #endif

		// 判断是否允许对应的登录方式
		checkLogintype(type) {
			return this.vuex_config.logintypearr && this.vuex_config.logintypearr.indexOf(type) > -1;
		}
	}
}

//文档列表
export const archives = {
	data() {
		return {
			tabwidth: 40,
			current: 0,
			status: 'nomore',
			page: 1,
			channel_id: 0,
			filterList: [],
			orderList: [],
			archivesList: [],
			is_show: false,
			has_more: false,
			scrollTop: 0,
			is_update: false,
			params: {},
			query: {},
			isTab: false,
			tabList: [],
			is_empty: false,
			channel: {},
		}
	},
	filters: {

	},
	computed: {
		is_order() {
			return this.filterList.length > 0 || this.orderList.length > 0;
		}
	},
	methods: {
		//切换导航Tab
		change(index) {
			//重设Bar宽度
			this.tabwidth = this.$util.strlen(this.tabList[index].title) * 30;
			this.current = index;
			this.channel_id = this.tabList[index].id;
			this.is_update = true;
			this.page = 1;
			this.getArchives();
		},
		//去排序
		goOrderBy(e) {
			this.page = 1;
			this.is_update = true;
			this.query = e;
			this.getArchives();
		},
		//获取文档列表
		getArchives: async function() {
			let data = {
				page: this.page,
				...this.params,
				...this.query
			};
			if (this.channel_id) {
				data.channel = this.channel_id;
			}
			let res = await this.$api.getArchives(data);
			this.status = 'nomore';
			uni.stopPullDownRefresh();
			if (!res.code) {
				return;
			}

			let { filterList, orderList, pageList, channel } = res.data;
			this.filterList = filterList;
			this.orderList = orderList;
			this.channel = channel;

			if (this.is_update) {
				this.is_update = false;
				this.archivesList = [];
			}
			this.is_show = true;
			this.has_more = pageList.current_page < pageList.last_page;
			this.archivesList = [...this.archivesList, ...pageList.data];
			this.is_empty = !this.archivesList.length;
		},
		//获取分类
		getCategory() {
			this.$api.getCategory({
				...this.params
			}).then(res => {
				if (res.code == 1) {
					this.tabList = res.data;
					this.isTab = true; //百度小程序要先有值
				} else {
					this.$u.toast(res.msg);
				}
			});
		},
		//打开页面
		openPage(index) {
			let path = this.bannerList[index].url;
			if (path == '/' || !path) {
				return;
			}
			if (['p', 'i', 'o'].includes(path.substr(0, 1))) {
				path = '/' + path;
			}
			if (path.indexOf('http') != -1) {
				this.$u.vuex('vuex_webs', this.bannerList[index]);
				path = '/pages/webview/webview';
			}
			let data = {},
				arr = path.split('?');
			if (arr[1]) {
				let q = arr[1].split('&');
				for (let item of q) {
					let param = item.split('=');
					data[param[0]] = param[1];
				}
			}
			this.$u.route(arr[0], data);
		},
	},
	//页面底部
	onPageScroll(e) {
		this.scrollTop = e.scrollTop;
	},
	//下拉刷新
	onPullDownRefresh() {
		this.is_update = true;
		this.page = 1;
		this.getArchives();
	},
	//到达页面底部
	onReachBottom() {
		if (this.has_more) {
			this.status = 'loading';
			this.page = ++this.page;
			this.getArchives();
		}
	}
}