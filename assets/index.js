Vue.use(window.VueInfiniteLoading)
const router = new VueRouter()
const tweetHistory = new Vue({
  el: '#tweetHistory',
  router: router,
  data: {
    apiUrl: './tweet2sqlite3.php',
    page: 1,
    tweets: [],
    allCount: 0,
    procTime: 0,
    abortController: undefined,
    searchModal: {
      query: '',
      since: '',
      until: '',
      targetId: '',
    },
    dontShowTweetModal: false,
    selectedTweet: undefined,
  },
  mounted: function() {
    this.resetPage()
  },
  watch: {
    $route: function() {
      this.resetPage()
    }
  },
  methods: {
    resetPage: function() {
      this.searchModal.query = this.$route.query.query
      this.searchModal.since = this.$route.query.since
      this.searchModal.until = this.$route.query.until
      this.searchModal.targetId = this.$route.query.targetId

      this.tweets = []
      this.allCount = 0
      this.procTime = 0
      this.page = 1
      this.$refs.infiniteLoading.stateChanger.reset()
    },
    infiniteHandler: function($state) {
      if(this.abortController !== undefined) {
        this.abortController.abort()
      }
      this.abortController = new AbortController()

      fetch(this.buildRequestUrl(), {signal: this.abortController.signal})
        .then(res => {
          if(res.ok) {
            return res.json()
          } else {
            this.$refs.infiniteLoading.stateChanger.error()
          }
        })
        .then(res => {
          const getData = res.data
          this.tweets = this.tweets.concat(getData.reverse())
          this.allCount = res.allCount
          this.procTime = res.procTime

          if(this.tweets.length == 0) {
            $state.complete()
          } else if(this.tweets.length >= this.allCount) {
            $state.loaded()
            $state.complete()
          } else {
            $state.loaded()
          }
          this.abortController = undefined
        })
        .catch(error => {
          if(!(error instanceof DOMException && error.code === 20)) {
            console.error(error)
            this.$refs.infiniteLoading.stateChanger.error()
          }
        })
    },
    buildRequestUrl: function() {
      let reqUrl = this.apiUrl + '?page=' + this.page++
      const append = (name => {
        const val = eval('this.$route.query.' + name)
        if(val !== undefined) {
          reqUrl += `&${name}=` + encodeURIComponent(val)
        }
      })
      append('query')
      append('since')
      append('until')
      append('targetId')
      return reqUrl
    },
    getFullText: function(tweet, removeMediaUrls = false) {
      let fullText = tweet.full_text
      if(this.hasUrl(tweet)) {
        tweet.entities.urls.forEach(url => {
          fullText = fullText.replace(url.url, url.expanded_url)
        })
      }
      if(this.hasPhoto(tweet)) {
        const photoUrls = []
        tweet.extended_entities.media.forEach(media => {
          photoUrls.push(media.media_url_https)
        })
        let replaceValue = ''
        if(!removeMediaUrls) {
          replaceValue = photoUrls.join(' ')
        }
        fullText = fullText.replace(tweet.extended_entities.media[0].url, replaceValue)
      } else if(this.hasVideo(tweet)) {
        let replaceValue = ''
        if(!removeMediaUrls) {
          replaceValue = this.getHiBitrateVideoUrl(tweet)
        }
        fullText = fullText.replace(tweet.extended_entities.media[0].url, replaceValue)
      }
      return fullText
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .trim()
    },
    hasUserMention: function(tweet) {
      return tweet.entities !== undefined && tweet.entities.user_mentions !== undefined
    },
    hasUrl: function(tweet) {
      return tweet.entities !== undefined && tweet.entities.urls !== undefined
    },
    hasPhoto: function(tweet) {
      return tweet.extended_entities !== undefined && tweet.extended_entities.media !== undefined && tweet.extended_entities.media[0].type === 'photo'
    },
    hasVideo: function(tweet) {
      return tweet.extended_entities !== undefined && tweet.extended_entities.media !== undefined && tweet.extended_entities.media[0].type === 'video'
    },
    getVideoAspect: function(tweet, delimiter = 'by') {
      const supportAspects = [
        [1, 1], [4, 3], [16, 9], [21, 9]
      ]
      const defaultAspect = [16, 9]
      const tweetAspect = tweet.extended_entities.media[0].video_info.aspect_ratio
      let isSupport = false
      supportAspects.forEach(aspect => {
        if(aspect[0] == tweetAspect[0] && aspect[1] == tweetAspect[1]) {
          isSupport = true
          return
        }
      })
      if(isSupport) {
        return tweetAspect.join(delimiter)
      } else {
        return defaultAspect.join(delimiter)
      }
    },
    getHiBitrateVideoUrl: function(tweet) {
      const variants = tweet.extended_entities.media[0].video_info.variants.concat()
      variants.sort((a, b) => {
        if(b.bitrate === undefined) return -1
        return b.bitrate - a.bitrate
      })
      return variants[0].url
    },
    getVia: function(tweet) {
      return tweet.source.replace(/<.+?>/g, '')
    },
    getDateTime: function(tweet) {
      const d = new Date(tweet.created_at)
      const yyyy = d.getFullYear()
      const mm = this.zeroPad(d.getMonth() + 1)
      const dd = this.zeroPad(d.getDate())
      const hh = this.zeroPad(d.getHours())
      const mi = this.zeroPad(d.getMinutes())
      const se = this.zeroPad(d.getSeconds())
      return `${yyyy}/${mm}/${dd} ${hh}:${mi}:${se}`
    },
    zeroPad: function(num) {
      return `0${num}`.slice(-2)
    },
    search: function() {
      const params = {}
      if(this.searchModal.targetId > 0) {
        params['targetId'] = this.searchModal.targetId
      } else {
        const setParam = (name => {
          const modalVal = eval('this.searchModal.' + name)
          if(modalVal != '') {
            params[name] = modalVal
          }
        })
        setParam('query')
        setParam('since')
        setParam('until')
      }

      const isSameQuery = JSON.stringify(params) == JSON.stringify(this.$route.query)
      if(Object.keys(params).length !== 0 && !isSameQuery) {
        router.push({query: params})
      }
    },
    clickMedia: function() {
      this.dontShowTweetModal = true
    },
    openTweetModal: function(tweet) {
      if(!this.dontShowTweetModal) {
        this.selectedTweet = tweet
        this.$refs.tweetModal.show()
      }
      this.dontShowTweetModal = false
    },
    closeTweetModal: function() {
      this.$refs.tweetModal.hide()
    },
    showBeforeAfterTweet: function(tweet) {
      this.searchModal.targetId = tweet.id
      this.search()
      this.closeTweetModal()
    },
  },
  computed: {
    allTweetCount: function() {
      return this.allCount.toLocaleString()
    },
    holdTweetCount: function() {
      return this.tweets.length.toLocaleString()
    },
  },
})
