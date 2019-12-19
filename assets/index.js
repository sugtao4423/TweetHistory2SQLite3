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
      const d = new Date(tweet.created_at)
      const yyyy = d.getFullYear()
      const mm = this.zeroPad(d.getMonth() + 1)
      const dd = this.zeroPad(d.getDate())
      const hh = this.zeroPad(d.getHours())
      const mi = this.zeroPad(d.getMinutes())
      const se = this.zeroPad(d.getSeconds())
      return `${yyyy}/${mm}/${dd} ${hh}:${mi}:${se}`
    },
    zeroPad: function(num){
      return `0${num}`.slice(-2)
    },
  },
})
