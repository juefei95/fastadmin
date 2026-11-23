module.exports = {
	computed: {
		cmsTitleStyle() {
			return val => {
				let style = {};
				if (val && val.includes('b')) {
					style.fontWeight = 'bold';
				}
				if (val && val.includes('#')) {
					style.color = val.replace('b', '').replace('|', '');
				}
				return style;
			}
		},
		theme() {
			let style = {};
			if (this.vuex_theme.value) {
				let theme = this.vuex_theme.value;
				let ladder = theme.ladder || 10;
				let number = theme.number || 9;
				let border = theme.border || 5;
				let colorArr = this.$u.colorGradient(theme.bgColor, theme.color, ladder);
				style = Object.assign({
					lightColor: colorArr[number] || '#f5f5f5',
					faBorderColor: colorArr[border] || '#f5f5f5'
				}, theme);
			} else {
				style = {
					bgColor: "#0301c5",
					color: "#ffffff",
					lightColor: '#f5f5f5',
					faBorderColor: '#f5f5f5'
				};
			}
			return style;
		},
		lightColor() {
			let color = '#f5f5f5';
			if (this.vuex_theme.value) {
				let theme = this.vuex_theme.value;
				let colorArr = this.$u.colorGradient(theme.bgColor, theme.color, 10);
				color = colorArr[9] || '#f5f5f5';
			}
			return color;
		},
		faBorderColor() {
			let color = '#f5f5f5';
			if (this.vuex_theme.value) {
				let theme = this.vuex_theme.value;
				let colorArr = this.$u.colorGradient(theme.bgColor, theme.color, 10);
				color = colorArr[5] || '#f5f5f5';
			}
			return color;
		},
	},
}