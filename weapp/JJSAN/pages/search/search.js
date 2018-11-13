var URL = require('../../utils/url.js');
var getUser = require('../../utils/getUser.js');
var holdUser = require('../../utils/holdUser.js');


Page({

  /**
   * 页面的初始数据
   */
  data: {
    user: '',
    array: [],
    longitude: '',
    latitude: '',
    searchInfo: '',
    value: '',
    count: 0,
    mark: 0,
    remind: 0
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {

    var page = this;

    page.fromNet = 0;
    getUser(page);
  },

  onShow: function () {
    var page = this;
    if (Date.now() - page.data.user.time > 1500000 || (!page.data.user && !page.working)) {
      page.fromNet = 1;
      getUser(page);
    }
  },

  //百度坐标转普通坐标
  rechangeLatitude: function (bd_lon, bd_lat) {
    var x_pi = 3.14159265358979324 * 3000.0 / 180.0;
    var x = bd_lon - 0.0065;
    var y = bd_lat - 0.006;
    var z = Math.sqrt(x * x + y * y) - 0.00002 * Math.sin(y * x_pi);
    var theta = Math.atan2(y, x) - 0.000003 * Math.cos(x * x_pi);
    var gg_lng = z * Math.cos(theta);
    var gg_lat = z * Math.sin(theta);
    return [gg_lng, gg_lat]
  },


  check: function (e) {
    var page = this;
    if (e.detail.value === '' || e.detail.value != page.data.value) {
      page.setData({
        array: [],
        count: 0,
        remind: 0
      })
    }
  },

  //跳转至商铺详情页面
  getStoreInfo: function (event) {
    var page = this;
    var id = event.currentTarget.id;
    wx.setStorage({
      key: 'store',
      data: page.data.array[id],
      success: function () {
        wx.navigateTo({
          url: '../store/store',
        });
      }
    })
  },

  //搜索事件
  search: function (event) {
    var page = this;
    var count = page.data.count;
    var mark = 0;
    var shops = [];
    var value = event.detail.value;

    wx.showNavigationBarLoading();

    if (value) {
      var times = 0;
      var timeOut = setTimeout(function fn() {
        wx.request({
          url: URL,
          data: {
            mod: 'api',
            act: 'weapp',
            opt: 'filter',
            session: page.data.user.session,
            key_str: value,
            mark: mark
          },
          success: function (res) {
            console.log('搜索事件', res);
            if (res.data.code == 0) {
              holdUser(page);

              count++;
              var array = res.data.data.shops;
              mark = res.data.data.mark;
              if (array.length < 30) {
                mark = null;
              }

              for (var i = 0; i < array.length; i++) {
                array[i].index = array[i].id;
                array[i].id = i + shops.length;
                array[i].call = array[i].title;
              }

              for (var i = 0; i < array.length; i++) {
                var temp = page.rechangeLatitude(array[i].lng, array[i].lat);
                array[i].longitude = temp[0];
                array[i].latitude = temp[1];
                shops.push(array[i]);
              }

              page.setData({
                value: value,
                array: shops,
                mark: mark,
                count: count
              });
              wx.hideNavigationBarLoading();
            } else if (res.data.code == 5) {
              if (!page.working) {
                page.fromNet = 1;
                getUser(page);
              }
              if (times < 100) {
                timeOut = setTimeout(fn, 50)
              }
            } else if (res.data.code == 2) {
              count++;
              page.setData({
                count: count,
                array: []
              });
              wx.hideNavigationBarLoading();
            }
          },
          fail: function () {
            if (times < 100) {
              timeOut = setTimeout(fn, 50)
            }
          },
          complete: function () {
            times++;
          }
        });
      }, 50);
    } else {
      wx.showToast({
        title: '查找内容不能为空',
      })
    }
  },

  loadMore: function () {

    var page = this;
    var mark = page.data.mark;
    var shops = page.data.array;
    var value = page.data.value;
    var count = page.data.count;

    var timeOut = page.data.timeOut;

    var callNow = !timeOut;

    timeOut = setTimeout(function () {
      page.setData({
        timeOut: null
      })
    }, 5000);

    page.setData({
      timeOut: timeOut
    })

    if (mark !== null) {
      if (callNow) {
        wx.showNavigationBarLoading();
        wx.request({
          url: URL,
          data: {
            mod: 'api',
            act: 'weapp',
            opt: 'filter',
            session: page.data.user.session,
            key_str: value,
            mark: mark
          },
          success: function (res) {

            if (res.data.code === 0) {
              holdUser(page);

              count++;
              var array = res.data.data.shops;
              mark = res.data.data.mark;
              if (array.length < 30) {
                mark = null;
              }

              for (var i = 0; i < array.length; i++) {
                array[i].index = array[i].id;
                array[i].id = i;
                array[i].call = array[i].title;
              }

              for (var i = 0; i < array.length; i++) {
                var temp = page.rechangeLatitude(array[i].lng, array[i].lat);
                array[i].longitude = temp[0];
                array[i].latitude = temp[1];
                shops.push(array[i]);
              }

              console.log('搜索出来的数据', array);
              page.setData({
                value: value,
                array: shops,
                mark: mark,
                count: count,
                timeOut: null
              });
            } else if (res.data.code == 5) {
              if (!page.working) {
                page.fromNet = 1;
                getUser(page);
              }
            } else if (res.data.code === 2) {
              mark = null;
              page.setData({
                mark: mark,
                remind: 1,
                timeOut: null
              });
              wx.showToast({
                title: '没有更多数据',
              }, 2000);
            }
            wx.hideNavigationBarLoading();
          },
          fail: function () {
            wx.hideNavigationBarLoading();
          }
        });
      }
    } else {
      if (page.data.remind == 0) {
        wx.showToast({
          title: '没有更多数据',
        }, 2000);
        page.setData({
          remind: 1,
          timeOut: null
        })
      }
    }
  }
})
