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
      show: false,
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
      this.closeModal()
    },
    infiniteHandler: function($state) {
      fetch(this.buildRequestUrl())
        .then((res) => {
          if(res.ok) {
            return res.json()
          } else {
            const err = res.json().error.message
            console.log(err)
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
      this.closeModal()
    },
    closeModal: function() {
      this.modal.show = false
    },
  },
})
