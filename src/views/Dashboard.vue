<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="state === 'loading'">
		<template v-slot:empty-content>
			<EmptyContent
				v-if="emptyContentMessage"
				:icon="emptyContentIcon">
				<template #desc>
					{{ emptyContentMessage }}
					<div v-if="state === 'no-token' || state === 'error'" class="connect-button">
						<a class="button" :href="settingsUrl">
							{{ t('integration_moodle', 'Connect to Moodle') }}
						</a>
					</div>
				</template>
			</EmptyContent>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { getLocale } from '@nextcloud/l10n'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget, EmptyContent,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			moodleUrl: null,
			upcomingEvents: [],
			recentItems: [],
			locale: getLocale(),
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/connected-accounts'),
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
		}
	},

	computed: {
		showMoreUrl() {
			return this.moodleUrl + '/web/notifications'
		},
		notifications() {
			return this.upcomingEvents.concat(this.recentItems)
		},
		items() {
			return this.notifications.map((n) => {
				return {
					id: this.getUniqueKey(n),
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getAuthorAvatarUrl(n),
					avatarUsername: this.getSubline(n),
					overlayIconUrl: this.getNotificationTypeImage(n),
					mainText: this.getMainText(n),
					subText: this.getSubline(n),
				}
			})
		},
		lastRecentTimestamp() {
			let maxTs = 0
			this.recentItems.forEach((n) => {
				if (n.time > maxTs) {
					maxTs = n.time
				}
			})
			return (maxTs === 0) ? null : maxTs
		},
		lastRecentMoment() {
			return moment.unix(this.lastRecentTimestamp)
		},
		emptyContentMessage() {
			if (this.state === 'no-token') {
				return t('integration_moodle', 'No Moodle account connected')
			} else if (this.state === 'error') {
				return t('integration_moodle', 'Error connecting to Moodle')
			} else if (this.state === 'ok') {
				return t('integration_moodle', 'No Moodle notifications!')
			}
			return ''
		},
		emptyContentIcon() {
			if (this.state === 'no-token') {
				return 'icon-moodle'
			} else if (this.state === 'error') {
				return 'icon-close'
			} else if (this.state === 'ok') {
				return 'icon-checkmark'
			}
			return 'icon-checkmark'
		},
	},

	beforeMount() {
		this.launchLoop()
	},

	mounted() {
	},

	methods: {
		async launchLoop() {
			// get moodle URL first
			try {
				const response = await axios.get(generateUrl('/apps/integration_moodle/url'))
				this.moodleUrl = response.data.replace(/\/+$/, '')
			} catch (error) {
				console.debug(error)
			}
			// then launch the loop
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 30000)
		},
		fetchNotifications() {
			const req = {}
			req.params = {
				recentSince: this.lastRecentTimestamp,
			}
			axios.get(generateUrl('/apps/integration_moodle/notifications'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_moodle', 'Failed to get Moodle notifications'))
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
			})
		},
		processNotifications(newNotifications) {
			// upcoming events: replace them inconditionally
			this.upcomingEvents = newNotifications.events

			// recent items: get the new ones
			if (this.lastRecentTimestamp) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newNotifications.recents.length && this.lastRecentTimestamp < newNotifications.recents[i].time) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filterRecentItems(newNotifications.recents.slice(0, i))
					this.recentItems = toAdd.concat(this.recentItems)
				}
			} else {
				this.recentItems = newNotifications.recents
			}
		},
		filterRecentItems(recentItems) {
			return recentItems
		},
		filterUpcomingEvents(upcomingEvents) {
			return upcomingEvents
		},
		getNotificationTarget(n) {
			if (['event', 'recent'].includes(n.type)) {
				return n.viewurl
			}
			return ''
		},
		getMainText(n) {
			if (['recent'].includes(n.type)) {
				return n.name
			} else if (['event'].includes(n.type)) {
				return '[' + this.getFormattedDate(n) + '] ' + n.name
			}
			return ''
		},
		getSubline(n) {
			if (['recent'].includes(n.type)) {
				return n.coursename
			} else if (['event'].includes(n.type)) {
				return n.course.fullname
			}
			return ''
		},
		html2text(s) {
			if (!s || s === '') {
				return ''
			}
			const temp = document.createElement('template')
			s = s.trim()
			temp.innerHTML = s
			return temp.content.firstChild.textContent
		},
		getUniqueKey(n) {
			return n.type + n.id
		},
		getAuthorAvatarUrl(n) {
			if (['event'].includes(n.type)) {
				return n.course.courseimage
			} else if (['recent'].includes(n.type)) {
				const el = document.createElement('img')
				el.innerHTML = n.icon
				const realUrl = el.firstChild.getAttribute('src')
				return generateUrl('/apps/integration_moodle/avatar?') + encodeURIComponent('url') + '=' + encodeURIComponent(realUrl)
			}
			return ''
		},
		getNotificationTypeImage(n) {
			if (n.type === 'event') {
				return generateUrl('/svg/integration_moodle/calendar?color=ffffff')
			} else if (['recent'].includes(n.type)) {
				return generateUrl('/svg/integration_moodle/time?color=ffffff')
			}
			return ''
		},
		getFormattedDate(n) {
			return moment.unix(n.time).locale(this.locale).format('L')
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep .connect-button {
	margin-top: 10px;
}
</style>
