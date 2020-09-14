<template>
	<div id="moodle_prefs" class="section">
		<h2>
			<a class="icon icon-moodle" />
			{{ t('integration_moodle', 'Moodle integration') }}
		</h2>
		<p v-if="!connected" class="settings-hint">
			{{ t('integration_moodle', 'Your password is not stored. It is just used once to get an access token which will be used to interact with your Moodle account.') }}
		</p>
		<div id="moodle-content">
			<div class="moodle-grid-form">
				<label for="moodle-url">
					<a class="icon icon-link" />
					{{ t('integration_moodle', 'Moodle instance address') }}
				</label>
				<input id="moodle-url"
					v-model="state.url"
					type="text"
					:disabled="connected === true"
					:placeholder="t('integration_moodle', 'Moodle instance URL')"
					@input="onInput">
				<label
					v-show="connected !== true"
					for="moodle-login">
					<a class="icon icon-user" />
					{{ t('integration_moodle', 'Moodle login') }}
				</label>
				<input
					v-show="connected !== true"
					id="moodle-login"
					v-model="login"
					type="text"
					:placeholder="t('integration_moodle', 'Your user name')"
					@keyup.enter="onValidate">
				<label
					v-show="connected !== true"
					for="moodle-password">
					<a class="icon icon-password" />
					{{ t('integration_moodle', 'Moodle password') }}
				</label>
				<input
					v-show="connected !== true"
					id="moodle-password"
					v-model="password"
					type="password"
					:placeholder="t('integration_moodle', 'Your password')"
					@keyup.enter="onValidate">
			</div>
			<button v-if="showConnect && !connected" @click="onValidate">
				<span class="icon icon-external" />
				{{ t('integration_moodle', 'Connect to Moodle') }}
			</button>
			<div v-if="connected" class="moodle-grid-form">
				<label class="moodle-connected">
					<a class="icon icon-checkmark-color" />
					{{ t('integration_moodle', 'Connected as {user}', { user: state.user_name }) }}
				</label>
				<button id="moodle-rm-cred" @click="onLogoutClick">
					<span class="icon icon-close" />
					{{ t('integration_moodle', 'Disconnect from Moodle') }}
				</button>
				<span />
			</div>
			<div v-if="connected" id="moodle-search-block">
				<input
					id="search-moodle"
					type="checkbox"
					class="checkbox"
					:checked="state.search_enabled"
					@input="onSearchChange">
				<label for="search-moodle">{{ t('integration_moodle', 'Enable unified search for courses.') }}</label>
			</div>
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

	computed: {
		showConnect() {
			return this.login && this.login !== ''
				&& this.password && this.password !== ''
		},
		connected() {
			return this.state.token && this.state.token !== ''
				&& this.state.url && this.state.url !== ''
				&& this.state.user_name && this.state.user_name !== ''
		},
	},

	methods: {
		onLogoutClick() {
			this.state.token = ''
			this.saveOptions()
		},
		onSearchChange(e) {
			this.state.search_enabled = e.target.checked
			this.saveOptions()
		},
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
					search_enabled: this.state.search_enabled ? '1' : '0',
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
					this.state.user_name = response.data.user_name
					this.password = ''
					showSuccess(t('integration_moodle', 'Moodle access token successfully retrieved!'))
				})
				.catch((error) => {
					let errorText = ''
					if (error.response && error.response.request && error.response.request.responseText) {
						const jsonError = JSON.parse(error.response.request.responseText)
						errorText = ': ' + jsonError.error
					}
					showError(
						t('integration_moodle', 'Failed to authenticate to Moodle')
						+ errorText
					)
				})
				.then(() => {
				})
		},
	},
}
</script>

<style scoped lang="scss">
#moodle-search-block {
	margin-top: 30px;
}
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
#moodle-content {
	margin-left: 40px;
}
</style>
