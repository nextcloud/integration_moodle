<template>
	<div id="moodle_prefs" class="section">
		<h2>
			<a class="icon icon-moodle" />
			{{ t('integration_moodle', 'Moodle integration') }}
		</h2>
		<div class="grid-form">
			<input
				id="disable-search"
				type="checkbox"
				class="checkbox"
				:checked="state.search_disabled"
				@input="onCheckSearchChange">
			<label for="disable-search">{{ t('integration_moodle', 'Disable search for all users') }}</label>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_moodle', 'admin-config'),
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onCheckSearchChange(e) {
			this.state.search_disabled = e.target.checked
			this.saveOptions({ search_disabled: this.state.search_disabled ? '1' : '0' })
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_moodle/admin-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_moodle', 'Moodle admin options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_moodle', 'Failed to save Moodle admin options')
						+ ': ' + (error.response?.request?.responseText ?? '')
					)
					console.debug(error)
				})
				.then(() => {
				})
		},
	},
}
</script>

<style scoped lang="scss">
.grid-form label {
	line-height: 38px;
}

.grid-form input {
	width: 100%;
}

.grid-form {
	max-width: 500px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	margin-left: 30px;
}

#moodle_prefs .icon {
	display: inline-block;
	width: 32px;
}

#moodle_prefs .grid-form .icon {
	margin-bottom: -3px;
}

.icon-moodle {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.theme--dark .icon-moodle {
	background-image: url(./../../img/app.svg);
}

</style>
