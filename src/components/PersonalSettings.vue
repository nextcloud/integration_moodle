<template>
	<div id="moodle_prefs" class="section">
		<h2>
			<a class="icon icon-moodle" />
			{{ t('integration_moodle', 'Moodle integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_moodle', 'Your login and password are not stored. They are just used once to get an access token which will be used to interact with your Moodle account.') }}
		</p>
		<div class="moodle-grid-form">
			<label for="moodle-url">
				<a class="icon icon-link" />
				{{ t('integration_moodle', 'Moodle instance address') }}
			</label>
			<input id="moodle-url"
				v-model="state.url"
				type="text"
				:placeholder="t('integration_moodle', 'Moodle instance URL')"
				@input="onInput">
			<label for="moodle-login">
				<a class="icon icon-user" />
				{{ t('integration_moodle', 'Moodle login') }}
			</label>
			<input id="moodle-login"
				v-model="login"
				type="text"
				:placeholder="t('integration_moodle', 'Your user name')"
				@keyup.enter="onValidate">
			<label for="moodle-password">
				<a class="icon icon-password" />
				{{ t('integration_moodle', 'Moodle password') }}
			</label>
			<input id="moodle-password"
				v-model="password"
				type="password"
				:placeholder="t('integration_moodle', 'Your password')"
				@keyup.enter="onValidate">
			<span />
			<button @click="onValidate">
				{{ t('integration_moodle', 'Get token') }}
			</button>
		</div>
		<br>
		<div class="moodle-grid-form">
			<label for="moodle-token">
				<a class="icon icon-category-auth" />
				{{ t('integration_moodle', 'Moodle access token') }}
			</label>
			<input id="moodle-token"
				v-model="state.token"
				type="password"
				:placeholder="t('integration_moodle', 'Authenticate with OAuth')"
				@input="onInput">
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_moodle', 'user-config'),
			login: '',
			password: '',
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			const that = this
			delay(function() {
				that.saveOptions()
			}, 2000)()
		},
		saveOptions() {
			if (!this.state.url.startsWith('https://')) {
				if (this.state.url.startsWith('http://')) {
					this.state.url = this.state.url.replace('http://', 'https://')
				} else {
					this.state.url = 'https://' + this.state.url
				}
			}
			const req = {
				values: {
					token: this.state.token,
					url: this.state.url,
				},
			}
			const url = generateUrl('/apps/integration_moodle/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_moodle', 'Moodle options saved.'))
				})
				.catch((error) => {
					showError(
						t('integration_moodle', 'Failed to save Moodle options')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		onValidate() {
			const req = {
				login: this.login,
				password: this.password,
			}
			const url = generateUrl('/apps/integration_moodle/get-token')
			axios.post(url, req)
				.then((response) => {
					this.state.token = response.data.token
					this.password = ''
					showSuccess(t('integration_moodle', 'Moodle access token successfully retrieved!'))
				})
				.catch((error) => {
					showError(
						t('integration_moodle', 'Failed to authenticate to Moodle')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
	},
}
</script>

<style scoped lang="scss">
.moodle-grid-form label {
	line-height: 38px;
}
.moodle-grid-form input {
	width: 100%;
}
.moodle-grid-form {
	max-width: 600px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	margin-left: 30px;
	button .icon {
		margin-bottom: -1px;
	}
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
body.dark .icon-moodle {
	background-image: url(./../../img/app.svg);
}
</style>
