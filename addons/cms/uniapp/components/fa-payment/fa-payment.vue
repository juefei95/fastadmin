<template>
	<view>
		<u-popup v-model="isShow" mode="center" border-radius="5" z-index="1000">
			<view class="payment" :style="[payStyle]">
				<view class="header u-flex u-row-between u-p-l-30 u-p-r-30 u-p-t-20 u-p-b-20">
					<view class="">{{ title }}</view>
					<u-icon name="close" @click="close"></u-icon>
				</view>
				<view class="u-p-30">
					<view class="alert-warning u-p-20 u-m-b-10">
						<view class="" v-if="subtitle">{{ subtitle }}</view>
						<view class="price u-m-t-10">需支付金额：￥{{ money }}</view>
					</view>

					<view class="u-border-top u-m-t-20">
						<u-radio-group v-model="paytype" class="group">
							<view class="group" v-for="(item, index) in paytypelist" :key="index">
								<u-cell-item :icon="item.icon" :icon-style="item.iconColor" :arrow="false" :title="item.name" @click="selectType(item.value)">
									<u-radio slot="right-icon" :active-color="theme.bgColor" label-size="30" :name="item.value">
										<u-icon size="30" name="arrow-right"></u-icon>
									</u-radio>
								</u-cell-item>
							</view>
						</u-radio-group>
					</view>
				</view>

				<view class="u-text-center u-p-30">
					<view class="">
						<u-button type="primary" hover-class="none" :custom-style="{ width: '80%', height: '60rpx', backgroundColor: theme.bgColor, color: theme.color }" size="mini" @click="submit">
							立即支付
						</u-button>
					</view>
					<view class="u-m-t-30" v-if="vip > 0">
						<u-button type="primary" hover-class="none" size="mini" :custom-style="{ width: '80%', height: '60rpx', backgroundColor: '#18b566', color: theme.color }" @click="goPage('/pages/my/member?vip='+vip)">
							升级VIP{{ vip }},免费阅读
						</u-button>
					</view>
				</view>
			</view>
		</u-popup>
	</view>
</template>

<script>
	import { loginfunc } from '@/common/fa.mixin.js'
	export default {
		mixins: [loginfunc],
		props: {
			//订单标题
			title: {
				type: String,
				default: '付费阅读'
			},
			//订单子标题
			subtitle: {
				type: String,
				default: ''
			},
			//订单金额
			money: {
				type: [String, Number],
				default: 0
			},
			//VIP等级
			vip: {
				type: [String, Number],
				default: 0
			},
			//来源
			source: {
				type: String,
				default: 'archives'
			},
			// 透传参数
			params: {
				type: Object,
				default: {}
			}
		},
		data() {
			return {
				isShow: false,
				paytype: ''
			};
		},
		computed: {
			paytypelist() {
				if (this.vuex_config.paytypelist) {
					let paytypelist = this.vuex_config.paytypelist.split(',');
					let list = [];
					paytypelist.map(item => {
						switch (item) {
							case 'wechat':
								// #ifdef APP-PLUS || MP-WEIXIN || H5
								list.push({
									name: '微信支付',
									value: 'wechat',
									icon: 'weixin-circle-fill',
									iconColor: { color: '#40BA49' }
								})
								// #endif
								break;
							case 'alipay':
								// #ifdef APP-PLUS || H5
								if (!this.isWeixin) {
									list.push({
										name: '支付宝支付',
										value: 'alipay',
										icon: 'zhifubao-circle-fill',
										iconColor: { color: '#00AAEE' }
									})
								}
								// #endif
								break;
							case 'balance':
								list.push({
									name: '余额支付',
									value: 'balance',
									icon: 'rmb-circle-fill',
									iconColor: { color: '#f39c12' }
								})
								break;
						}
					});
					return list;
				}
				return [];
			},
			isWeixin() {
				// #ifndef MP-WEIXIN
				return this.$util.isWeiXinBrowser()
				// #endif

				// #ifdef MP-WEIXIN
				return true;
				// #endif
			},
			payStyle() {
				let style = {};
				if (this.vip > 0) {
					style.height = this.paytypelist.length == 3 ? '870rpx' : this.paytypelist.length == 2 ? '770rpx' : '670rpx';
				} else {
					style.height = this.paytypelist.length == 3 ? '770rpx' : this.paytypelist.length == 2 ? '670rpx' : '570rpx'
				}
				return style;
			},
		},
		methods: {
			show() {
				this.checkDefaultPaytype();

				// #ifdef H5
				//如果在微信内，未设置openid则跳转获取openid
				if (!this.vuex_openid && this.$util.isWeiXinBrowser()) {
					this.goAuth('/pages/login/openid', 'snsapi_base');
					return;
				}
				// #endif

				this.isShow = true;
			},
			close() {
				this.isShow = false;
			},
			selectType(paytype) {
				this.paytype = paytype;
			},
			paySuccess() {
				console.log("paySuccess");
				this.close();
				this.$emit('paySuccess');
				this.close();
			},
			checkDefaultPaytype() {
				if (this.vuex_config.defaultpaytype) {
					this.paytype = this.vuex_config.defaultpaytype;
				}
				let available = [];
				let list = this.paytypelist;
				list.map(item => {
					available.push(item.value);
				});
				if (available.indexOf(this.paytype) === -1) {
					this.paytype = available[0];
				}
			},
			submit: async function() {
				if (!this.paytype) {
					this.$u.toast('请选择支付方式！');
					return;
				}
				let data = Object.assign({
					paytype: this.paytype,
					openid: this.vuex_openid,
					method: 'web'
				}, this.params || {});

				// #ifdef APP-PLUS
				data.appid = plus.runtime.appid;
				data.method = 'app';
				// #endif

				// #ifdef MP-WEIXIN
				data.method = 'miniapp';
				//获取openid
				data.openid = this.vuex_openid || await this.getWechatOpenid();
				// #endif

				// #ifdef H5
				data.method = 'web';
				//如果为微信支付，且在微信内则指定为公众号支付
				data.method = this.paytype == 'wechat' && this.$util.isWeiXinBrowser() ? 'mp' : data.method;
				// #endif

				//提交订单
				let res;
				if (this.source === 'archives') {
					res = await this.$api.getArchivesOrder(data);
				} else if (this.source === 'vip') {
					res = await this.$api.goVipSubmit(data);
				}
				if (!res) {
					this.$u.toast('参数错误！');
					return;
				}

				if (!res.code) {
					this.$u.toast(res.msg);
					return;
				}

				//余额支付
				if (this.paytype == 'balance') {
					this.$u.toast('支付成功！');
					this.paySuccess();
					return;
				}

				// #ifdef MP-WEIXIN
				uni.requestPayment({
					provider: 'wxpay',
					timeStamp: res.data.timeStamp,
					nonceStr: res.data.nonceStr,
					package: res.data.package,
					signType: res.data.signType,
					paySign: res.data.paySign,
					success: res => {
						this.$u.toast('支付成功！');
						this.paySuccess();
					},
					fail: err => {
						console.log('fail:' + JSON.stringify(err));
						this.$u.toast('支付失败');
					}
				});
				// #endif

				// #ifdef APP-PLUS
				uni.requestPayment({
					provider: this.paytype == 'alipay' ? 'alipay' : 'wxpay',
					orderInfo: res.data, //微信、支付宝订单数据
					success: res => {
						this.$u.toast('支付成功！');
						this.paySuccess();
					},
					fail: function(err) {
						console.log('fail:' + JSON.stringify(err));
						this.$u.toast('支付失败');
					}
				});
				// #endif

				// #ifdef H5
				if (this.$util.isWeiXinBrowser() && this.paytype == 'wechat') {
					//在微信环境，且为微信支付
					window.WeixinJSBridge.invoke(
						'getBrandWCPayRequest', {
							appId: res.data.appId,
							timeStamp: res.data.timeStamp,
							nonceStr: res.data.nonceStr,
							package: res.data.package,
							signType: res.data.signType,
							paySign: res.data.paySign
						},
						res => {
							console.log(res);
							if (res.err_msg === 'get_brand_wcpay_request:ok') {
								this.$u.toast('支付成功！');
								this.paySuccess();
							} else if (res.err_msg === 'get_brand_wcpay_request:cancel') {
								this.$u.toast('取消支付');
							} else {
								this.$u.toast('支付失败');
							}
						}
					);
				} else {
					//非微信环境的wap 支付方法，会返回orderid，再模拟表单提交
					data.returnurl = window.location.href;

					//URL地址
					if (res.data.toString().match(/^((?:[a-z]+:)?\/\/)(.*)/i)) {
						location.href = res.data;
						return;
					}

					if (typeof res.code !== 'undefined' && res.code === 0) {
						console.log(res);
						this.$u.toast(res.msg || "支付失败");
						return;
					}

					if (typeof res.data.message !== 'undefined') {
						this.$u.toast(res.data.message || "支付失败");
						return;
					}

					//Form表单
					document.getElementsByTagName('body')[0].innerHTML = res.data;
					let form = document.querySelector('form');
					if (form && form.length > 0) {
						form.submit();
						return;
					}

					//Meta跳转
					let meta = document.querySelector('meta[http-equiv="refresh"]');
					if (meta && meta.length > 0) {
						setTimeout(function() {
							location.href = meta.content.split(/;/)[1];
						}, 300);
						return;
					}
				}
				// #endif
			}
		}
	}
</script>

<style lang="scss">
	.payment {
		width: 600rpx;

		.header {
			border-bottom: 1px solid #eee;
			background-color: #f8f8f8;
		}

		.alert-info {
			background-color: #d9edf7;
			border-color: #bce8f1;
			color: #3498db;
			border-radius: 6rpx;
		}

		.alert-warning {
			background-color: #fcf8e3;
			border-color: #faebcc;
			color: #f39c12;
			border-radius: 6rpx;
		}

		.price {
			color: red;
		}

		.group {
			width: 100%;
		}
	}
</style>