var URL = require('../../utils/url.js');
var getUser = require('../../utils/getUser.js');
var getLocation = require('../../utils/getLocation.js');
var holdUser = require('../../utils/holdUser.js');


Page({

  data: {
    user: '',
    cache: [],
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
   * 加载数据
   */
  onLoad: function (options) {
    console.log('onload')

    var page = this;

    page.fromNet = 0;
    getUser(page);

    wx.getStorage({
      key: 'nearby',
      success: function (res) {
        console.log('获得的商铺数据', res.data)
        page.setData({
          cache: res.data,
          array: res.data
        });
      },
      fail: function () {
        getLocation(page);
        page.getNearby();
      }
    });
  },

  onShow: function () {
    var page = this;
    if (Date.now() - page.data.user.time > 1500000 || (!page.data.user && !page.working)) {
      page.fromNet = 1;
      getUser(page);
    }
  },

  //获取最近的JJ伞列表
  getNearby: function () {
    var page = this;
    var times = 0;

    var getStore = setTimeout(function fn() {
      if (page.data.user && page.data.longitude && page.data.latitude) {
        var dest = page.changeLatitude(page.data.longitude, page.data.latitude);
        var lng = dest[0];
        var lat = dest[1];
        wx.showNavigationBarLoading();
        wx.request({
          url: URL,
          data: {
            mod: 'api',
            act: 'weapp',
            opt: 'get_shops',
            session: page.data.user.session,
            lng: lng,
            lat: lat
          },
          success: function (res) {
            if (res.data.code === 0) {
              wx.hideNavigationBarLoading();

              holdUser(page);

              console.log('获取附近商铺数据', res);
              var result = res.data.data;
              var len = result.length;

              for (var i = 0; i < len; i++) {
                var temp = page.rechangeLatitude(result[i].longitude, result[i].latitude);
                result[i].longitude = temp[0];
                result[i].latitude = temp[1];
              }

              for (var i = 0; i < len; i++) {
                result[i].distance = page.getDistance(lat, lng, result[i].latitude, result[i].longitude);
                result[i].distance = Math.round(result[i].distance);

                result[i].call = result[i].title;
                delete result[i].title;
                if (result[i].call.length > 12) {
                  result[i].call = result[i].call.substring(0, 12);
                }
              }

              for (var i = 0; i < len; i++) {
                for (var j = 0; j < len - 1 - i; j++) {
                  if (result[j].distance > result[j + 1].distance) {
                    var temp = result[j + 1];
                    result[j + 1] = result[j];
                    result[j] = temp;
                  }
                }
              }

              for (var i = 0; i < len; i++) {
                console.log(result[i].distance);
                result[i].index = result[i].id;
                result[i].id = i;
                if (result[i].distance < 1000) {
                  result[i].distance = result[i].distance + 'm';
                } else if (result[i].distance > 999000) {
                  result[i].distance = 999 + '+km';
                } else {
                  result[i].distance = (result[i].distance / 1000).toFixed(1) + 'km';
                }

                result[i].width = 40;
                result[i].height = 40;
                result[i].iconPath = "../../assets/img/umbrella.png";
              }

              console.log('整理后的附近的商铺数据', result)

              page.setData({
                cache: result,
                array: result
              });

              wx.setStorage({
                key: 'nearby',
                data: result
              });
            } else if (res.data.code == 5) {
              if (page.working === 0) {
                page.fromNet = 1;
                getUser(page);
              }
              if (times < 100) {
                getStore = setTimeout(fn, 50);
              }
            }
          },
          fail: function () {
            if (times < 100) {
              getStore = setTimeout(fn, 50);
            }
          }
        })
      } else {
        if (times < 100) {
          getStore = setTimeout(fn, 50);
        }
      }
    }, 50);
  },

  check: function (e) {
    var page = this;
    if (e.detail.value == '' || e.detail.value != page.data.value) {
      page.setData({
        array: page.data.cache,
        count: 0,
        value: '',
        remind: 0
      })
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

  toRad: function (d) {
    return d * Math.PI / 180;
  },

  getDisance: function (lat1, lng1, lat2, lng2) {
    var page = this;
    var dis = 0;
    var radLat1 = page.toRad(lat1);
    var radLat2 = page.toRad(lat2);
    var deltaLat = radLat1 - radLat2;
    var deltaLng = page.toRad(lng1) - page.toRad(lng2);
    var dis = 2 * Math.asin(Math.sqrt(Math.pow(Math.sin(deltaLat / 2), 2) + Math.cos(radLat1) * Math.cos(radLat2) * Math.pow(Math.sin(deltaLng / 2), 2)));
    return dis * 6378137;
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
    console.log('value', value);

    var timeOut = page.data.timeOut;

    var callNow = !timeOut;

    if (value) {
      wx.showNavigationBarLoading();
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
            console.log('搜索返回数据', res);
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

              page.setData({
                array: shops,
                mark: mark,
                count: count,
                value: value
              });
              wx.hideNavigationBarLoading();
            } else if (res.data.code == 5) {
              if (!page.working) {
                page.fromNet = 1;
                getUser(page);
              }
              if (time < 100) {
                timeOut = setTimeout(fn, 50);
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
            if (time < 100) {
              timeOut = setTimeout(fn, 50);
            }
          },
          complete: function () {
            times++;
          }
        });
      }, 50);
    } else {
      wx.showToast({
        title: '搜索内容不能为空',
      })
    }

  },
  loadMore: function () {
    console.log('loadMore');
    var page = this;
    var count = page.data.count;
    var mark = page.data.mark;
    var shops = page.data.array;
    var value = page.data.value;
    console.log(count, mark, shops, value);

    var timeOut = page.data.timeOut;

    var callNow = !timeOut;

    timeOut = setTimeout(function () {
      page.setData({
        timeOut: null
      })
    }, 5000);

    page.setData({
      timeOut: timeOut
    });

    if (count > 0) {
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
              wx.hideNavigationBarLoading();
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
                  array[i].id = i + shops.length;
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
                  array: shops,
                  mark: mark,
                  count: count,
                  value: value,
                  timeOut: null
                });
                wx.hideNavigationBarLoading();
              } else if (res.data.code == 5) {
                if (!page.working) {
                  page.fromNet = 1;
                  getUser(page);
                }
              } else if (res.data.code == 2) {
                count++;
                mark = null;
                page.setData({
                  mark: mark,
                  count: count,
                  value: value,
                  timeOut: null
                });
                if (page.data.remind == 0) {
                  wx.showToast({
                    title: '没有更多数据',
                  }, 2000);
                  page.setData({
                    remind: 1,
                  })
                }
              }
            }
          });
        }
      } else {
        if (page.data.remind == 0) {
          wx.showToast({
            title: '没有更多数据',
          }, 2000);
          page.setData({
            remind: 1
          })
        }
      }
    }
  }
})