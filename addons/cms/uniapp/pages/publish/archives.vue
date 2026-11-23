<template>
	<view>
		<!-- 顶部导航 -->
		<fa-navbar :title="title" ref="navbar"></fa-navbar>
		<view class="u-p-30" v-if="showForm">
			<u-form :model="form" :rules="rules" ref="uForm" :errorType="errorType">
				<!-- 自定义字段 -->
				<block v-for="(item, index) in fields" :key="index">
					<!-- 字符 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'string' && showField(item, index)">
						<u-input type="text" :border="border" :placeholder="item.tip || '请填写' + item.title" v-model="form[item.name]"></u-input>
					</u-form-item>
					<!-- 定位 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'location' && showField(item, index)">
						<u-input type="select" :border="border" :placeholder="item.tip || '请填写' + item.title" v-model="form[item.name]" @click="openmap(item.name)"></u-input>
					</u-form-item>
					<!-- 文本 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'text' && showField(item, index)">
						<u-input type="textarea" :border="border" :placeholder="item.tip || '请填写' + item.title" v-model="form[item.name]"></u-input>
					</u-form-item>
					<!-- 编辑器 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'editor' && showField(item, index)">
						<fa-editor v-model="form[item.name]" :html="item.value"></fa-editor>
					</u-form-item>
					<!-- 数组 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'array' && showField(item, index) && item.name !== 'downloadurl'">
						<fa-array :faKey="item.setting.key" :faVal="item.setting.value" v-model="form[item.name]" :showValue="item.value || item.content_list"></fa-array>
					</u-form-item>
					<!-- 数组（下载） -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :label="item.title" :required="item.required" v-if="item.type == 'array' && showField(item, index) && item.name == 'downloadurl'">
						<fa-array-download v-model="form[item.name]" :showValue="item.value" :contentList="item.content_list"></fa-array-download>
					</u-form-item>
					<!-- 标签 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'tags' && showField(item, index)">
						<fa-tags v-model="form.tags" :tagList="archives && archives.tags"></fa-tags>
					</u-form-item>
					<!-- 日期 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'date' && showField(item, index)">
						<u-input :border="border" type="select" :select-open="showPicker && mode == 'date'" v-model="form[item.name]" :placeholder="item.tip || '请选择' + item.title" @click="selectPicker('date', item.name)"></u-input>
					</u-form-item>
					<!-- 时间 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'time' && showField(item, index)">
						<u-input :border="border" type="select" :select-open="showPicker && mode == 'time'" v-model="form[item.name]" :placeholder="item.tip || '请选择' + item.title" @click="selectPicker('time', item.name)"></u-input>
					</u-form-item>
					<!-- 日期时间 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'datetime' && showField(item, index)">
						<u-input :border="border" type="select" :select-open="showPicker && mode == 'datetime'" v-model="form[item.name]" :placeholder="item.tip || '请选择' + item.title" @click="selectPicker('datetime', item.name)"></u-input>
					</u-form-item>
					<!-- 日期区间 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'datetimerange' && showField(item, index)">
						<u-input :border="border" type="select" :select-open="calendarShow" v-model="form[item.name]" :placeholder="item.tip || '请选择' + item.title" @click="calendarShow = true;time_field = item.name;"></u-input>
					</u-form-item>
					<!-- 数字 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'number' && showField(item, index)">
						<u-input type="number" :border="border" :placeholder="item.tip || '请填写' + item.title" v-model="form[item.name]"></u-input>
					</u-form-item>
					<!-- 多选框 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'checkbox' && showField(item, index)">
						<fa-check-radio :faList="item.content_list" v-model="form[item.name]" :checkValue="item.value || item.defaultvalue"></fa-check-radio>
					</u-form-item>
					<!-- 单选框 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'radio' && showField(item, index)">
						<fa-check-radio :faList="item.content_list" type="radio" v-model="form[item.name]" :checkValue="item.value || item.defaultvalue"></fa-check-radio>
					</u-form-item>
					<!-- 列表单选 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'select' && showField(item, index)">
						<fa-selects :fa-list="item.content_list" :title="item.title" :checkeType="item.type" :showValue="item.value || item.defaultvalue" v-model="form[item.name]"></fa-selects>
					</u-form-item>
					<!-- 列表多选 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'selects' && showField(item, index)">
						<fa-selects :fa-list="item.content_list" :title="item.title" :checkeType="item.type" :showValue="item.value || item.defaultvalue" v-model="form[item.name]"></fa-selects>
					</u-form-item>
					<!-- 单图 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'image' && showField(item, index)">
						<fa-upload-image v-model="form[item.name]" :file-list="item.value"></fa-upload-image>
					</u-form-item>
					<!-- 多图 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'images' && showField(item, index)">
						<fa-upload-image v-model="form[item.name]" imgType="many" :file-list="item.value"></fa-upload-image>
					</u-form-item>
					<!-- #ifdef APP-PLUS || H5 || MP-WEIXIN -->
					<!-- 单文件 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'file' && showField(item, index)">
						<fa-upload-file v-model="form[item.name]" :isDom="true" :showValue="item.value"></fa-upload-file>
					</u-form-item>
					<!-- 多文件 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'files' && showField(item, index)">
						<fa-upload-file v-model="form[item.name]" fileType="many" :isDom="true" :showValue="item.value"></fa-upload-file>
					</u-form-item>
					<!-- #endif -->
					<!-- 开关 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'switch' && showField(item, index)">
						<fa-switch v-model="form[item.name]" :defvalue="item.value || 0"></fa-switch>
					</u-form-item>
					<!-- 关联城市 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'city' && showField(item, index)">
						<u-input :border="border" type="select" :select-open="cityShow" v-model="form[item.name]" :placeholder="item.tip || '请选择' + item.title" @click="
								cityShow = true;
								city_field = item.name;
							"></u-input>
					</u-form-item>
					<!-- 关联单选 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'selectpage' && showField(item, index)">
						<fa-selectpages :fa-id="item.id" :title="item.title" :checkeType="item.type" :showField="item.setting.field" :keyField="item.setting.primarykey" :showValue="(form[item.name] ? form[item.name] : item.value) || item.defaultvalue" v-model="form[item.name]"></fa-selectpages>
					</u-form-item>
					<!-- 关联多选 -->
					<u-form-item :label-position="labelPosition" label-width="130" :prop="item.name" :required="item.required" :label="item.title" v-if="item.type == 'selectpages' && showField(item, index)">
						<fa-selectpages :fa-id="item.id" :title="item.title" :checkeType="item.type" :showField="item.setting.field" :keyField="item.setting.primarykey" :showValue="(form[item.name] ? form[item.name] : item.value) || item.defaultvalue" v-model="form[item.name]"></fa-selectpages>
					</u-form-item>
				</block>
			</u-form>
			<view class="u-p-30 u-m-t-30">
				<u-button type="primary" hover-class="none" :custom-style="{ backgroundColor: theme.bgColor, color: theme.color }" shape="circle" @click="submit">
					提交
				</u-button>
			</view>
		</view>
		<u-picker v-model="showPicker" mode="time" :params="params" @confirm="pickerResult"></u-picker>
		<u-calendar v-model="calendarShow" mode="range" @change="calendarResult" max-date="3000-01-01"></u-calendar>
		<!-- 城市 -->
		<fa-citys v-model="cityShow" @city-change="cityResult"></fa-citys>
		<!-- 底部导航 -->
		<fa-tabbar></fa-tabbar>
	</view>
</template>

<script>
	import { formRule } from '@/common/fa.mixin.js';
	import FaArray from './components/fa-array/fa-array.vue';
	import FaArrayDownload from './components/fa-array-download/fa-array-download.vue';
	import FaCheckRadio from './components/fa-check-radio/fa-check-radio.vue';
	import FaCitys from './components/fa-citys/fa-citys.vue';
	import FaEditor from './components/fa-editor/fa-editor.vue';
	import FaFile from './components/fa-file/fa-file.vue';
	import FaSelectpages from './components/fa-selectpages/fa-selectpages.vue';
	import FaSelects from './components/fa-selects/fa-selects.vue';
	import FaSwitch from './components/fa-switch/fa-switch.vue';
	import FaTags from './components/fa-tags/fa-tags.vue';
	import FaUploadFile from './components/fa-upload-file/fa-upload-file.vue';
	import FaUploadImage from './components/fa-upload-image/fa-upload-image.vue';
	export default {
		components: {
			FaArray,
			FaArrayDownload,
			FaCheckRadio,
			FaCitys,
			FaEditor,
			FaFile,
			FaSelectpages,
			FaSelects,
			FaSwitch,
			FaTags,
			FaUploadFile,
			FaUploadImage
		},
		mixins: [formRule],
		onLoad(e) {
			let query = e || {};
			this.archives_id = query.archives_id || 0;
			if (this.archives_id) {
				this.title = "编辑文章";
			}
			this.getChannelFields();
		},
		data() {
			return {

				archives_id: '',
				labelPosition: 'top',
				border: false,
				errorType: ['message'],
				showForm: false,
				title: "发布文章",
				// 表单字段
				fields: [],
				values: {},
				//项目信息
				archives: {},
				form: {},
				rules: {},
				rules_bak: {},

				calendarShow: false,
				showPicker: false,
				mode: '',
				time_field: '',
				params: {},
				cityShow: false,
				city_field: '',
				imageList: {}
			};
		},
		methods: {
			showField(item, index) {
				let visible = false;
				if (item.favisible && item.favisible.length > 0) {
					let success = 0;
					for (let i = 0; i < item.favisible.length; i++) {
						let subitem = item.favisible[i];
						switch (subitem.operate) {
							case "=":
							case "==":
								if (this.form[subitem.field] == subitem.value) {
									success++;
								}
								break;
							case ">":
								if (this.form[subitem.field] > subitem.value) {
									success++;
								}
								break;
							case ">=":
								if (this.form[subitem.field] >= subitem.value) {
									success++;
								}
								break;
							case "<":
								if (this.form[subitem.field] < subitem.value) {
									success++;
								}
								break;
							case "<=":
								if (this.form[subitem.field] <= subitem.value) {
									success++;
								}
								break;
							case "in":
								if (this.form[subitem.field] && this.form[subitem.field].indexOf(subitem.value) > -1) {
									success++;
								}
								break;
							case "regex":
								let reg = new RegExp(subitem.value);
								if (this.form[subitem.field] && this.form[subitem.field].match(reg)) {
									success++;
								}
								break;
							default:
								break;
						}
						if (i == success) {
							break;
						}
						if (success == item.favisible.length) {
							visible = true;
						}
					}
				} else {
					visible = true;
				}
				for (var i = 0; i < this.rules[item.name].length; i++) {
					this.rules[item.name][i].required = (visible ? (this.rules_bak[item.name][i].required || false) : false);
					this.fields[index].required = (visible ? (this.rules_bak[item.name][i].required || false) : false);
				}
				return visible;
			},
			//获取字段
			getChannelFields() {
				this.$api.getChannelFields({
					channel_id: this.vuex_channel_id,
					archives_id: this.archives_id
				}).then(res => {
					if (res.code) {
						this.values = res.data.values;
						this.archives = res.data.archives;
						this.fields = res.data.fields;

						//渲染自定义字段
						let custom_form = {};
						let rules = {};
						this.fields.map(item => {
							//表单赋值
							if (item.type == 'number') {
								custom_form[item.name] = item.value || item.defaultvalue || 0;
							} else {
								custom_form[item.name] = item.value || item.defaultvalue || '';
							}

							//单图赋值
							if (item.type == 'image') {
								if (item.value) {
									item.value = [{
										url: this.cdnurl(item.value)
									}];
								} else {
									item.value = [];
								}
							}
							//多图赋值
							if (item.type == 'images') {
								if (item.value) {
									let images = item.value.split(',');
									let urls = [];
									images.forEach(it => {
										urls.push({
											url: this.cdnurl(it)
										});
									});
									item.value = urls;
								} else {
									item.value = [];
								}
							}
							//单文件
							if (item.type == 'file') {
								item.value = item.value ? [item.value] : [];
							}
							//多文件
							if (item.type == 'files') {
								if (item.value) {
									item.value = item.value.split(',');
								} else {
									item.value = [];
								}
							}
							//默认值
							item.required = false;
							//追加自定义表单验证
							rules[item.name] = this.getRules(item);
							console.log(item, item.rule)
							//判断验证规则是否必选
							rules[item.name].map(rule => {
								if (rule.required) {
									item.required = true;
								}
							});
						});
						console.log(custom_form, rules)

						this.form = custom_form;
						this.rules = rules;
						this.rules_bak = this.$u.deepClone(rules);
						this.showForm = true;
						//设置表单验证规则
						console.log(this.form, this.rules, this.fields);
						this.$nextTick(() => {
							this.$refs.uForm.setRules(this.rules);
						});
					}
				});
			},
			//标签数据
			tagsChange(e) {
				this.$set(this.form, 'tags', e.join(','));
			},
			//时间显示
			selectPicker(mode, field) {
				this.mode = mode;
				this.time_field = field;
				switch (mode) {
					case 'date':
						this.params = {
							year: true,
							month: true,
							day: true,
							hour: false,
							minute: false,
							second: false
						};
						break;
					case 'time':
						this.params = {
							year: false,
							month: false,
							day: false,
							hour: true,
							minute: true,
							second: true
						};
						break;
					case 'datetime':
						this.params = {
							year: true,
							month: true,
							day: true,
							hour: true,
							minute: true,
							second: true
						};
						break;
				}
				this.showPicker = true;
			},
			//时间的选择结果
			pickerResult(e) {
				switch (this.mode) {
					case 'date':
						this.$set(this.form, this.time_field, e.year + '-' + e.month + '-' + e.day);
						break;
					case 'time':
						this.$set(this.form, this.time_field, e.hour + ':' + e.minute + ':' + e.second);
						break;
					case 'datetime':
						this.$set(this.form, this.time_field, e.year + '-' + e.month + '-' + e.day + ' ' + e.hour + ':' + e.minute + ':' + e.second);
						break;
				}
			},
			openmap(name) {
				let that = this;
				uni.chooseLocation({
					success: function(res) {
						that.$set(that.form, name, `${res.address},${res.latitude},${res.longitude}`);
					},
					fail: (e) => {
						console.log(e);
					}
				});
			},
			//时间范围选择的结果
			calendarResult(e) {
				this.$set(this.form, this.time_field, e.startDate + ' 00:00:00 - ' + e.endDate + ' 23:59:59');
			},
			//城市选择
			cityResult(e) {
				this.$set(this.form, this.city_field, e.province.label + '/' + e.city.label + '/' + e.area.label);
			},
			//提交
			submit: async function() {
				console.log('验证开始', this.form);
				this.$nextTick(() => {
					this.$refs.uForm.setRules(this.rules);
				});
				//校验
				this.$refs.uForm.validate(valid => {
					if (valid) {
						console.log('验证通过', this.form);
						this.$api.archivesPost(Object.assign({ channel_id: this.vuex_channel_id, id: this.archives_id }, this.form)).then(res => {
							this.$u.toast(res.msg);
							if (res.code) {
								setTimeout(() => {
									uni.navigateBack({
										delta: 2,
										success: () => {
											let pages = getCurrentPages();
											let prevPage = pages[pages.length - 3]; //跳转页面成功之后刷新
											console.log(prevPage);
											prevPage && typeof prevPage.$vm.reload === 'function' && prevPage.$vm.reload();
										}
									})
								}, 1500);
							}
						});
					} else {
						console.log('验证失败', this.form);
					}
				});
			}
		}
	};
</script>

<style lang="scss">
	page {
		background-color: #ffffff;
	}
</style>