Vue.use(window.VueInfiniteLoading)
const router = new VueRouter()
const tweetHistory = new Vue({
  el: '#tweetHistory',
  router: router,
  data: {
    apiUrl: './tweet2sqlite3.php',
    page: 1,
    tweets: [],
    procTime: 0,
    modal: {
      query: '',
    },
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
      this.modal.query = this.$route.query.query

      this.tweets = []
      this.page = 1
      this.$refs.infiniteLoading.stateChanger.reset()
    },
    infiniteHandler: function($state) {
      fetch(this.buildRequestUrl())
        .then((res) => {
          if(res.ok) {
            return res.json()
          } else {
            this.$refs.infiniteLoading.stateChanger.error()
          }
        })
        .then((res) => {
          const getData = res.data
          if(getData.length === 0) {
            $state.complete()
          }
          this.tweets = this.tweets.concat(getData.reverse())
          this.procTime = res.procTime
        })
        .finally(() => {
          $state.loaded()
        })
    },
    buildRequestUrl: function() {
      let reqUrl = this.apiUrl + '?page=' + this.page++
      const query = this.$route.query.query
      if(query !== undefined) {
        reqUrl += '&query=' + encodeURIComponent(query)
      }
      return reqUrl
    },
    hasPhoto: function(tweet) {
      return tweet.extended_entities !== undefined && tweet.extended_entities.media !== undefined && tweet.extended_entities.media[0].type === 'photo'
    },
    hasVideo: function(tweet) {
      return tweet.extended_entities !== undefined && tweet.extended_entities.media !== undefined && tweet.extended_entities.media[0].type === 'video'
    },
    getVideoAspect: function(tweet, delimiter = 'by') {
      return tweet.extended_entities.media[0].video_info.aspect_ratio.join(delimiter)
    },
    getHiBitrateVideoUrl: function(tweet) {
      const variants = tweet.extended_entities.media[0].video_info.variants.concat()
      variants.sort((a, b) => {
        if(b.bitrate === undefined) return -1
        return b.bitrate - a.bitrate
      })
      return variants[0].url
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
      const params = []
      const searchQuery = this.modal.query
      if(searchQuery != '' && searchQuery !== this.$route.query.query) {
        params['query'] = searchQuery
      }
      if(Object.keys(params).length !== 0) {
        router.push({query: params})
      }
    },
  },
})
