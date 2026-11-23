<template>
	<view>
		<!-- 顶部导航 -->
		<fa-navbar :title="vuex_table_title || '产品'" ref="navbar"></fa-navbar>
		<view class="u-p-20 u-bg-white u-flex u-col-center" v-if="is_show">
			<view class="u-flex-1">
				<fa-search :mode="2"></fa-search>
			</view>
			<view class="u-p-l-15 u-p-r-5 u-flex u-col-center" v-if="is_order">
				<fa-orderby-select :filterList="filterList" :orderList="orderList" :multiple="true" @change="goOrderBy"></fa-orderby-select>
			</view>
		</view>
		<!-- 分类 -->
		<view class="u-border-top" v-if="isTab">
			<u-tabs :list="tabList" :bar-width="tabwidth" :active-color="theme.bgColor" name="title" :is-scroll="true" :current="current" @change="change"></u-tabs>
		</view>
		<view class="u-flex u-flex-wrap u-p-30">
			<view class="product-item" v-for="(item, index) in archivesList" :key="index" @click="goDetail(item.id)">
				<u-image width="100%" border-radius="8" height="210" :src="item.image"></u-image>
				<view class="title">{{ item.title }}</view>
			</view>
		</view>
		<!-- 为空 -->
		<view class="u-m-t-60 u-p-t-60" v-if="is_empty">
			<u-empty text="暂无内容展示" mode="list"></u-empty>
		</view>
		<!-- 加载更多 -->
		<view class="u-p-b-30" v-if="archivesList.length">
			<u-loadmore bg-color="#f4f6f8" :status="status" />
		</view>
		<!-- 回到顶部 -->
		<u-back-top :scroll-top="scrollTop" :icon-style="{ color: theme.bgColor }" :custom-style="{ backgroundColor: lightColor }"></u-back-top>
		<!-- 底部导航 -->
		<fa-tabbar></fa-tabbar>
	</view>
</template>

<script>
	import { archives } from '@/common/fa.mixin.js';
	export default {
		mixins: [archives],
		computed: {

		},
		data() {
			return {};
		},
		onLoad(e) {
			let query = e;
			if (JSON.stringify(query) == '{}') {
				query = e;
			}
			if (query && JSON.stringify(query) != '{}') {
				this.params = query;
			} else {
				this.params = {
					channel: -1,
					model: -1,
					menu_index: 2
				};
			}
			this.getCategory();
			this.getArchives();
		},
		methods: {
			goDetail(id) {
				this.goPage('/pages/product/detail?id=' + id);
			},
		},
	};
</script>

<style lang="scss">
	page {
		background-color: #f4f6f8;
	}

	.product-item {
		width: 50%;
		position: relative;

		.title {
			position: absolute;
			font-size: 20rpx;
			bottom: 20rpx;
			background: #000;
			width: auto;
			max-width: 50%;
			padding-left: 8rpx;
			padding-right: 8rpx;
			color: #f8f8f8;
			opacity: 0.7;
			height: 50rpx;
			line-height: 50rpx;
			overflow: hidden;
			text-align: center;
			border-top-right-radius: 6rpx;
			border-bottom-right-radius: 6rpx;
		}
	}

	.product-item:nth-child(2n + 1) {
		padding-right: 15rpx;
	}

	.product-item:nth-child(2n) {
		padding-left: 15rpx;
	}

	.product-item:not(:last-child) {
		margin-bottom: 30rpx;
	}
</style>