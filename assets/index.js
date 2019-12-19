Vue.use(window.VueInfiniteLoading)
const tweetHistory = new Vue({
  el: '#tweetHistory',
  data: {
    apiUrl: './tweet2sqlite3.php',
    page: 1,
    tweets: [],
    procTime: 0,
  },
  methods: {
    infiniteHandler: function($state) {
      const reqUrl = this.apiUrl + '?page=' + this.page++

      fetch(reqUrl)
        .then((res) => {
          if(res.ok) {
            return res.json()
          } else {
            const err = res.json().error.message
            console.log(err)
          }
        })
        .then((res) => {
          this.tweets = this.tweets.concat(res.data.reverse());
          this.procTime = res.procTime
        })
        .finally(() => {
          $state.loaded()
        })
    },
    getDateTime: function(tweet) {
      return new Date(tweet.created_at).toLocaleString()
    }
  },
})
