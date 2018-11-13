var URL = require('../../utils/url.js');
var getUser = require('../../utils/getUser.js');
var getLocation = require('../../utils/getLocation.js');
var holdUser = require('../../utils/holdUser.js');


//获取应用实例
var app = getApp()
Page({
  /**
   * 页面的初始数据
   */
  data: {
    controls: [],
    city: '',
    latitude: "",
    longitude: "",
    name: '',
    markers: [],
    now: '',
    address: '',
    usable: '',
    empty: '',
    distance: '',
    show: false,
    destinationlat: '',
    destinationlong: '',
    session: '',
    user: '',
    wallet: ''
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {

    this.mapCtx = wx.createMapContext('myMap');
    this.ctx = wx.createCanvasContext('canvas');

    var that = this;

    wx.getSystemInfo({
      success: function (res) {
        console.log('设备信息数据', res);

        that.setData({
          pixelRatio: res.pixelRatio,
          screenWidth: res.windowWidth,
          screenHeight: res.windowHeight
        });

        // var pixelRatio = res.pixelRatio;
        // var windowWidth = res.windowWidth;
        // var windowHeight = res.windowHeight;

        // that.ctx.setFillStyle('#FFFFFF');
        // that.ctx.rect(0, 0, windowWidth, 360);
        // that.ctx.fill();

        // that.ctx.setFillStyle('#2a2929');
        // that.ctx.setFontSize(14);
        // that.ctx.fillText('街借伞',12,24);

        // that.ctx.drawImage('../../assets/img/canlend.png',12,30,28,28);
        // that.ctx.setFontSize(13);
        // that.ctx.fillText('可借:12', 50, 49);

        // that.ctx.drawImage('../../assets/img/canreturn.png', 12, 60, 28, 28);
        // that.ctx.setFontSize(13);
        // that.ctx.fillText('可还:4', 50, 78);

        // that.ctx.draw();
      }
    });
    that.fromNet = 0;
    getUser(that);
    getLocation(that);
    that.getNearby();
  },

  onShow: function () {
    var page = this;
    wx.setNavigationBarTitle({
      title: '附近网点',
    });

    var screenWidth = page.data.screenWidth;
    var screenHeight = page.data.screenHeight;

    var controls = [
      {
        id: 1,
        iconPath: '../../assets/img/search.png',
        position: {
          left: 12,
          top: 10,
          width: screenWidth - 24,
          height: 40
        },
        clickable: true
      },
      {
        id: 2,
        iconPath: '../../assets/img/personal.png',
        position: {
          left: 12,
          top: 60,
          width: 40,
          height: 40
        },
        clickable: true
      },
      {
        id: 3,
        iconPath: '../../assets/img/locate.png',
        position: {
          left: 12,
          top: screenHeight - 80,
          width: 40,
          height: 40
        },
        clickable: true
      },
      {
        id: 4,
        iconPath: '../../assets/img/scan.png',
        position: {
          left: screenWidth / 2 - 66,
          top: screenHeight - 102,
          width: 132,
          height: 62
        },
        clickable: true
      },
      {
        id: 5,
        iconPath: '../../assets/img/change.png',
        position: {
          left: screenWidth - 52,
          top: screenHeight - 80,
          width: 40,
          height: 40
        },
        clickable: true
      },
      {
        id: 6,
        iconPath: '../../assets/img/location.png',
        position: {
          left: screenWidth / 2 - 7,
          top: screenHeight / 2 - 36,
          width: 30,
          height: 40
        },
        clickable: false
      }
    ]

    page.setData({
      controls: controls
    })

    if (Date.now() - page.data.user.time > 1500000 || (!page.data.user && !page.working)) {
      page.fromNet = 1;
      getUser(page);
    }
    getLocation(page);

    var array = page.data.markers;
    for (var i = 0; i < array.length; i++) {
      array[i].iconPath = "../../assets/img/umbrella.png";
    };
    page.setData({
      markers: array
    });

    if (array.length > 0) {
      wx.setStorage({
        key: 'nearby',
        data: array,
      })
    }
  },

  toRad: function (d) {
    return d * Math.PI / 180;
  },

  getDistance: function (lat1, lng1, lat2, lng2) {
    var page = this;
    var dis = 0;
    var radLat1 = page.toRad(lat1);
    var radLat2 = page.toRad(lat2);
    var deltaLat = radLat1 - radLat2;
    var deltaLng = page.toRad(lng1) - page.toRad(lng2);
    var dis = 2 * Math.asin(Math.sqrt(Math.pow(Math.sin(deltaLat / 2), 2) + Math.cos(radLat1) * Math.cos(radLat2) * Math.pow(Math.sin(deltaLng / 2), 2)));
    return dis * 6378137;
  },


  //腾讯坐标转换成百度坐标
  changeLatitude: function (lng, lat) {
    var x_PI = 3.14159265358979324 * 3000.0 / 180.0;
    var z = Math.sqrt(lng * lng + lat * lat) + 0.00002 * Math.sin(lat * x_PI);
    var theta = Math.atan2(lat, lng) + 0.000003 * Math.cos(lng * x_PI);
    var bd_lng = z * Math.cos(theta) + 0.0065;
    var bd_lat = z * Math.sin(theta) + 0.006;
    return [bd_lng, bd_lat]
  },

  //百度坐标转换成腾讯坐标
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

  //获取最近的JJ伞列表
  getNearby: function () {
    var page = this;

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
              clearInterval(getStore);
              page.mapCtx.moveToLocation();
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
                markers: result
              });

              wx.setStorage({
                key: 'nearby',
                data: result
              });
            } else {
              if (!page.working) {
                page.fromNet = 1;
                getUser(page)
              }
              getStore = setTimeout(fn, 50);
            }
          },
          fail: function () {
            getStore = setTimeout(fn, 50);
          }
        })
      } else {
        getStore = setTimeout(fn, 50);
      }
    }, 50);
  },

  tap: function () {
    var page = this;
    var array = page.data.markers;

    for (var i = 0; i < array.length; i++) {
      array[i].iconPath = "../../assets/img/umbrella.png";
    }

    page.setData({
      markers: array
    });

  },

  //地图图标点击事件
  makertap: function (res) {
    var id = res.markerId;
    var page = this;
    var array = page.data.markers;
    console.log('id', id);
    console.log('marker', array);

    for (var i = 0; i < array.length; i++) {
      array[i].iconPath = "../../assets/img/umbrella.png";
    }

    array[id].iconPath = "../../assets/img/umbrellachosen.png";
    page.setData({
      markers: array
    });

    wx.getSetting({
      success: function (res) {
        if (res.authSetting['scope.userInfo'] && res.authSetting['scope.userLocation']) {
          wx.showActionSheet({
            itemList: [array[id].call, '可借:' + array[id].usable + '，' + '可还:' + array[id].empty],
            itemColor: "#000000",
            success: function (res) {
              if (res.tapIndex === 0 || res.tapIndex === 1) {
                wx.setStorage({
                  key: 'store',
                  data: array[id],
                  success: function () {
                    wx.navigateTo({
                      url: '../store/store',
                    });
                  }
                });
              }
            }
          });
        } else {
          wx.showModal({
            showCancel: false,
            title: '温馨提示',
            content: '您已拒绝微信（公开信息，地理位置）授权，请在关于借伞的设置中进入小程序授权并开启手机位置信息。',
            success: function () {
              wx.openSetting({
                success: function (res) {
                  if (res.authSetting['scope.userInfo'] && res.authSetting['scope.userLocation']) {
                    getLocation(page);
                    getUser(page);
                    page.getNearby();
                  } else if (res.authSetting['scope.userInfo']) {
                    getUser(page);
                  } else if (res.authSetting['scope.userLocation']) {
                    getLocation(page);
                  }
                }
              });
            }
          });
        }
      }
    });
  },

  //移动地图事件
  move: function (e) {

    console.log('move', e);
    var page = this;

    var user = page.data.user;
    var map = this.mapCtx;

    var timeout = page.data.timeout;

    if (timeout) {
      clearTimeout(timeout);
    }

    timeout = setTimeout(function () {
      console.log('移动一次');
      if (e.type == 'end') {
        if (user.session) {
          console.log('开始获取')
          map.getCenterLocation({
            success: function (res) {
              console.log('移动地图', res);
              var dest = page.changeLatitude(res.longitude, res.latitude);
              var lng = dest[0];
              var lat = dest[1];

              var distance = page.getDistance(page.data.latitude, page.data.longitude, res.latitude, res.longitude);
              console.log('距离', distance);

              if (distance > 500) {
                wx.showNavigationBarLoading();
                wx.request({
                  url: URL,
                  data: {
                    mod: 'api',
                    act: 'weapp',
                    opt: 'get_shops',
                    session: user.session,
                    lng: lng,
                    lat: lat
                  },
                  success: function (res) {
                    console.log('移动地图所获得的', res);
                    if (res.data.code === 0) {
                      holdUser(page);

                      var result = res.data.data;

                      var len = result.length;
                      for (var i = 0; i < len; i++) {
                        result[i].distance = page.getDistance(page.data.latitude, page.data.longitude, result[i].latitude, result[i].longitude);
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
                        var lac = page.rechangeLatitude(result[i].longitude, result[i].latitude);
                        result[i].longitude = lac[0];
                        result[i].latitude = lac[1];
                      }
                      console.log('附近的商铺数据', result)

                      page.setData({
                        markers: result
                      });
                    } else if (res.data.code === 5) {
                      if (!page.working) {
                        page.fromNet = 1;
                        getUser(page);
                      }
                    }
                  },
                  complete: function () {
                    wx.hideNavigationBarLoading();
                  }
                })
              }
            }
          })
        }
      }
    }, 1000);

    page.setData({
      timeout: timeout
    })
  },

  //图标点击事件
  controltap: function (e) {
    var page = this;
    switch (e.controlId) {
      case 1:
        wx.getSetting({
          success: function (res) {
            if (res.authSetting['scope.userInfo'] && res.authSetting['scope.userLocation']) {
              wx.navigateTo({
                url: '../search/search'
              });
            } else {
              wx.showModal({
                showCancel: false,
                title: '温馨提示',
                content: '您已拒绝微信（公开信息，地理位置）授权，请在关于借伞的设置中进入小程序授权并开启手机位置信息。',
                success: function () {
                  wx.openSetting({
                    success: function (res) {
                      if (res.authSetting['scope.userInfo'] && res.authSetting['scope.userLocation']) {
                        getLocation(page);
                        getUser(page);
                        page.getNearby();
                      } else if (res.authSetting['scope.userInfo']) {
                        getUser(page);
                      } else if (res.authSetting['scope.userLocation']) {
                        getLocation(page);
                      }
                    }
                  });
                }
              });
            }
          }
        });
        break;

      case 2:
        wx.getSetting({
          success: function (res) {
            if (res.authSetting['scope.userInfo'] && res.authSetting['scope.userLocation']) {
              wx.navigateTo({
                url: '../personal/personal'
              });
            } else {
              wx.showModal({
                showCancel: false,
                title: '温馨提示',
                content: '您已拒绝微信（公开信息，地理位置）授权，请在关于借伞的设置中进入小程序授权并开启手机位置信息。',
                success: function () {
                  wx.openSetting({
                    success: function (res) {
                      if (res.authSetting['scope.userInfo'] && res.authSetting['scope.userLocation']) {
                        getLocation(page);
                        getUser(page);
                        page.getNearby();
                      } else if (res.authSetting['scope.userInfo']) {
                        getUser(page);
                      } else if (res.authSetting['scope.userLocation']) {
                        getLocation(page);
                      }
                    }
                  });
                }
              });
            }
          }
        });
        break;

      case 3:
        wx.getSetting({
          success: function (res) {
            if (res.authSetting['scope.userInfo'] && res.authSetting['scope.userLocation']) {
              getLocation(page);
              page.getNearby();
              page.mapCtx.moveToLocation();
            } else {
              wx.showModal({
                showCancel: false,
                title: '温馨提示',
                content: '您已拒绝微信（公开信息，地理位置）授权，请在关于借伞的设置中进入小程序授权并开启手机位置信息。',
                success: function () {
                  wx.openSetting({
                    success: function (res) {
                      if (res.authSetting['scope.userInfo'] && res.authSetting['scope.userLocation']) {
                        getLocation(page);
                        getUser(page);
                        page.getNearby();
                        page.mapCtx.moveToLocation();
                      } else if (res.authSetting['scope.userInfo']) {
                        getUser(page);
                      } else if (res.authSetting['scope.userLocation']) {
                        getLocation(page);
                        page.mapCtx.moveToLocation();
                      }
                    }
                  });
                }
              });
            }
          }
        });
        break;

      case 4:
        wx.getSetting({
          success: function (res) {
            if (res.authSetting['scope.userInfo'] && res.authSetting['scope.userLocation']) {
              wx.scanCode({
                onlyFromCamera: true,
                success: (res) => {
                  console.log('二维码', res);
                  var times = 0;
                  var timeOut = setTimeout(function fn() {
                    wx.request({
                      url: URL,
                      data: {
                        mod: 'api',
                        act: 'weapp',
                        opt: 'borrow',
                        qrcode: res.result,
                        session: page.data.user.session,
                      },
                      success: function (res) {
                        console.log('请求成功:', res);
                        var code = res.data.code;
                        if (code == 5) {
                          if (!page.working) {
                            page.fromNet = 1;
                            getUser(page);
                          }
                          if (times < 100) {
                            timeOut = setTimeout(fn, 50);
                          }
                        } else if (code == 555) {
                          wx.showToast({
                            title: '二维码错误',
                            duration: 2000
                          });
                        } else {
                          var wallet;
                          if (code === 0) {
                            holdUser(page);
                            wallet = res.data.data;
                          } else {
                            wallet = {};
                          }
                          wallet.code = res.data.code;
                          console.log('存取的wallet', wallet);
                          wx.setStorage({
                            key: 'wallet',
                            data: wallet,
                            success: function () {
                              wx.navigateTo({
                                url: '../pay/pay',
                              })
                            }
                          });
                        }
                      },
                      fail: function fn() {
                        if (times < 100) {
                          timeOut = setTimeout(fn, 50);
                        }
                      },
                      complete: function () {
                        times++;
                      }
                    });
                  }, 50)
                }
              });
            } else {
              wx.showModal({
                showCancel: false,
                title: '温馨提示',
                content: '您已拒绝微信（公开信息，地理位置）授权，请在关于借伞的设置中进入小程序授权并开启手机位置信息。',
                success: function () {
                  wx.openSetting({
                    success: function (res) {
                      if (res.authSetting['scope.userInfo'] && res.authSetting['scope.userLocation']) {
                        getLocation(page);
                        getUser(page);
                        page.getNearby();
                      } else if (res.authSetting['scope.userInfo']) {
                        getUser(page);
                      } else if (res.authSetting['scope.userLocation']) {
                        getLocation(page);
                      }
                    }
                  });
                }
              });
            }
          }
        });
        break;

      case 5:
        wx.getSetting({
          success: function (res) {
            if (res.authSetting['scope.userInfo'] && res.authSetting['scope.userLocation']) {
              wx.navigateTo({
                url: '../nearby/nearby',
              });
            } else {
              wx.showModal({
                showCancel: false,
                title: '温馨提示',
                content: '您已拒绝微信（公开信息，地理位置）授权，请在关于借伞的设置中进入小程序授权并开启手机位置信息。',
                success: function () {
                  wx.openSetting({
                    success: function (res) {
                      if (res.authSetting['scope.userInfo'] && res.authSetting['scope.userLocation']) {
                        getLocation(page);
                        getUser(page);
                        page.getNearby();
                      } else if (res.authSetting['scope.userInfo']) {
                        getUser(page);
                      } else if (res.authSetting['scope.userLocation']) {
                        getLocation(page);
                      }
                    }
                  });
                }
              });
            }
          }
        });
        break;
    }
  },

  onHide: function () {
    var page = this;
    var array = page.data.markers;
    for (var i = 0; i < array.length; i++) {
      array[i].iconPath = "../../assets/img/umbrella.png";
    }
    page.setData({
      markers: array,
    });
  }
})