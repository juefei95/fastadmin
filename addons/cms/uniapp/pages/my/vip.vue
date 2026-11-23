<template>
	<view>
		<!-- 顶部导航 -->
		<fa-navbar title="我的VIP会员" ref="navbar"></fa-navbar>
		<view class="u-p-30">
			<view class="center u-p-30" :style="[{ background: theme.bgColor }]">
				<view class="u-flex">
					<view class="u-m-r-15">
						<u-avatar size="90" :src="vuex_user.avatar"></u-avatar>
					</view>
					<view class="u-flex u-flex-column u-row-between u-col-top u-flex-1">
						<view class="u-flex">
							<view class="nickname">{{ vuex_user.nickname }}</view>
							<view class="u-m-l-10" v-if="vipInfo.level">
								<u-tag :text="vipInfo.name" mode="dark" size="mini" type="success" />
							</view>
						</view>
						<view class="u-m-t-10 u-font-22 u-flex u-row-between" style="width: 100%;">
							<view class="">VIP等级： {{ vipInfo.level || 0 }}</view>
							<view class="remainseconds" v-if="vipInfo.remainseconds && vipInfo.remainseconds < 86400">
								<u-count-down font-size="22" separator-color="#ffffff" :timestamp="vipInfo.remainseconds || 0"></u-count-down>
							</view>
						</view>
					</view>
				</view>
				<view class="u-flex u-row-between u-m-t-15">
					<view class="">{{ vipInfo.expiredate | expireRender }}</view>
					<view class="">
						<u-button size="mini" shape="circle" hover-class="none" v-if="vipInfo.level" @click="goBuy(1)">续费会员</u-button>
						<u-button size="mini" shape="circle" hover-class="none" v-else @click="goBuy(0)">购买会员</u-button>
					</view>
				</view>
			</view>
		</view>
		<block v-if="isVipList(1)">
			<view class="u-p-30 bg-title">选择购买会员</view>
			<view class="u-border-bottom u-p-t-20 u-p-b-20">
				<u-tabs :list="vipList" :active-color="theme.bgColor" :bar-width="tabwidth" :is-scroll="isVipList(4)" :current="current" @change="change"></u-tabs>
			</view>
		</block>
		<view class="u-flex u-row-between u-p-30 bg-title">
			<view class="">选择开通时长</view>
			<view class="">
				
			</view>
		</view>
		<view class="u-p-l-30 u-p-r-30 u-p-t-30">
			<view class="list u-flex u-flex-wrap">
				<view class="item u-text-center" :style="[itemStyle(index)]" v-for="(item, index) in pricedata" :key="index" @click="selectVip(item, index)">
					<view class="">{{ item.title }}</view>
					<view class="u-p-t-15 u-p-b-15 price">
						<text class="u-font-20">￥</text>
						<text class="u-font-40 u-text-weight">{{ item.price }}</text>
					</view>
					<view class="u-font-20 u-tips-color u-line-2">{{ item.subtext }}</view>
				</view>
			</view>
		</view>
		<view class="u-p-30 bg-title">获得权益</view>
		<view class="">
			<view class="interests u-p-30 u-flex u-flex-wrap">
				<view class="item u-text-center u-m-t-20" v-for="(item, index) in rightdata" :key="index">
					<view class="u-flex u-row-center">
						<u-image width="80rpx" height="80rpx" :src="item.image"></u-image>
					</view>
					<view class="u-font-30 u-text-weight u-m-t-10 u-m-b-10">{{ item.text }}</view>
					<view class="u-font-20 u-tips-color u-line-3">{{ item.intro }}</view>
				</view>
			</view>
		</view>

		<u-gap height="180" bg-color="#f3f4f6"></u-gap>
		<view class="vip-footer u-p-30 u-border-top">
			<u-button shape="circle" type="primary" hover-class="none" :custom-style="{ backgroundColor: isDisabled ? '#ccc' : theme.bgColor, color: theme.color }" @click="goBuy(0)">
				{{ nowLevel }}
			</u-button>
		</view>
		<!-- 支付 -->
		<fa-payment ref="faPayment" source="vip" :title="title" :subtitle="subtitle" :money="money" :params="{ 'level':level,'days':days }" @paySuccess="paySuccess"></fa-payment>
	</view>
</template>

<script>
	import { loginfunc } from '@/common/fa.mixin.js';
	export default {
		mixins: [loginfunc],
		data() {
			return {
				vip: 0,
				tabwidth: 120,
				vipList: [],
				pricedata: [],
				rightdata: [],
				//用户的VIP信息
				vipInfo: {},
				current: 0,
				selectIndex: [],
				title: '购买VIP',
				subtitle: '',
				money: 0,
				level: 0,
				days: 0
			};
		},
		onLoad(e) {
			this.vip = e.vip || 0;
			this.getVipIndex();
		},
		computed: {
			itemStyle() {
				return index => {
					let style = {
						height: '250rpx'
					};

					if (index == this.selectIndex[this.current]) {
						style.border = '1px solid ' + this.theme.bgColor;
						style.backgroundColor = this.lightColor;
					}

					return style;
				};
			},
			isVipList() {
				return index => {
					return this.vipList.length > index;
				};
			},
			nowLevel() {
				if (this.isDisabled) {
					return '不可操作';
				}
				if (!this.vipInfo.level) {
					return '立即购买';
				}
				if (this.vipList.length > 0 && this.vipInfo.level == this.vipList[this.current].level) {
					return '我要续费';
				}
				return '我要升级';
			},
			isDisabled() {
				if (this.vipInfo.level && this.vipList[this.current].level < this.vipInfo.level) {
					return true;
				}
				return false;
			},
		},
		filters: {
			expireRender(value) {
				if (!value) {
					return '请先购买会员吧！';
				}
				let obj1 = new Date(value.replace(/\-/g, "/"));
				let obj2 = new Date();
				let time1 = obj1.getFullYear() + '-' + (obj1.getMonth() + 1) + '-' + obj1.getDate();
				let time2 = obj2.getFullYear() + '-' + (obj2.getMonth() + 1) + '-' + obj2.getDate();

				if (time1 == time2) {
					return '今天过期，尽快续费哦！';
				}
				if (obj1 - obj2 < 0) {
					return '会员已过期，请续费哦！';
				}
				return time1 + ' 后过期';
			}
		},
		methods: {
			change(index) {
				this.tabwidth = this.$util.strlen(this.vipList[index].name) * 30;
				this.current = index;
				this.setPriceData();
			},
			//支付成功
			paySuccess() {
				console.log(123);
				this.getVipIndex();
			},
			//购买支付
			goBuy(type) {
				let data = {
					level: this.vipList[this.current].level,
					days: this.pricedata[this.selectIndex[this.current]].days,
					money: this.pricedata[this.selectIndex[this.current]].price
				};
				
				//续费
				if (type == 1) {
					this.vipList.some((item, index) => {
						if (item.level == this.vipInfo.level) {
							data.level = item.level;
							//如果选择过，用选择的，没有用默认的天数
							if (typeof this.selectIndex[index] != 'undefined') {
								data.days = item.pricedata[this.selectIndex[index]].days;
							} else {
								item.pricedata.some(res => {
									if (this.getIsDefault(res.default)) {
										data.days = res.days;
										return true;
									}
								});
							}
							return true;
						}
					});
				}
				this.level = data.level;
				this.days = data.days;
				this.money= data.money;

				//显示支付
				this.$refs.faPayment.show();
			},
			getIsDefault(value) {
				return [true, 1, '1', 'yes', 'true'].indexOf(value) != -1;
			},
			getVipIndex() {
				this.$api.getVipIndex().then(res => {
					if (res.code) {
						this.paytypelist = res.data.config.paytypelist;
						this.paytype = res.data.config.defaultpaytype;
						//有vip等级传来，自动到VIP
						res.data.vipList.some((item, index) => {
							if (item.level == this.vip) {
								this.current = index;
								return true;
							}
						});
						this.vipList = res.data.vipList || [];
						this.vipInfo = res.data.vipInfo || {};
						this.setPriceData();
					}
				});
			},
			//渲染数据
			setPriceData() {
				this.vipList.length > this.current && (this.pricedata = this.vipList[this.current].pricedata) && (this.rightdata = this.vipList[this.current].rightdata);
				if (typeof this.selectIndex[this.current] == 'undefined') {
					let defaultIndex = 0;
					//渲染默认的index
					this.pricedata.some((item, index) => {
						if (this.getIsDefault(item.default)) {
							defaultIndex = index;
							return true;
						}
					});
					this.$set(this.selectIndex, this.current, defaultIndex);
				}
			},
			//选择
			selectVip(item, index) {
				this.$set(this.selectIndex, this.current, index);
			},
		}
	};
</script>

<style lang="scss" scoped>
	.center {
		border-radius: 20rpx;
		color: #ffffff;
		box-shadow: 0 0 16rpx 4rpx rgba(55, 68, 134, 0.3);

		.nickname {
			font-weight: bold;
		}
	}

	.u-flex-column {
		flex-direction: column;
		height: 100%;
	}

	.bg-title {
		background-color: #f3f4f6;
	}

	.list {
		.item {
			border: 1px solid #cccccc;
			width: calc((100vw - 120rpx) / 3);
			padding: 40rpx 30rpx;
			margin-bottom: 30rpx;
			border-radius: 10rpx;

			&:nth-child(3n-1) {
				margin-left: 30rpx;
				margin-right: 30rpx;
			}

			.price {
				color: #fc4c57;
			}
		}
	}

	.interests {
		.item {
			width: calc((100vw - 120rpx) / 3);
			margin-bottom: 30rpx;
			height: 200rpx;

			&:nth-child(3n-1) {
				margin-left: 30rpx;
				margin-right: 30rpx;
			}
		}
	}

	.vip-footer {
		position: fixed;
		left: 0;
		bottom: 0;
		width: 100%;
		background-color: #ffffff;
		z-index: 999;
	}
</style>