<template>
	<view class="">
		<!-- 顶部导航 -->
		<fa-navbar :title="vuex_table_title || '首页'" ref="navbar"></fa-navbar>
		<!-- 搜索 -->
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
			<u-tabs :list="tabList" :active-color="theme.bgColor" :bar-width="tabwidth" name="title" :is-scroll="true" :current="current" @change="change"></u-tabs>
		</view>
		<!-- 轮播图 -->
		<view class="" v-if="is_show">
			<u-swiper :title="true" border-radius="0" height="320" :list="bannerList" @click="openPage"></u-swiper>
		</view>
		<!-- 列表 -->
		<fa-article-item :archives-list="archivesList"></fa-article-item>
		<!-- 为空 -->
		<view class="u-m-t-60 u-p-t-60" v-if="is_empty">
			<u-empty text="暂无内容展示" mode="list"></u-empty>
		</view>
		<!-- 加载更多 -->
		<view class="u-p-30" v-if="archivesList.length">
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
			bannerList() {
				return this.vuex_config.bannerList || [];
			}
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
					model: -1
				}
			}
			this.getCategory();
			this.getArchives();
		},
		methods: {},
	};
</script>

<style lang="scss">
	page {
		background-color: #f4f6f8;
	}
</style>