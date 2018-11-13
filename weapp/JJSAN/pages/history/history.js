var URL = require('../../utils/url.js');
var getUser = require('../../utils/getUser.js');
var holdUser = require('../../utils/holdUser.js');

Page({

  /**
   * 页面的初始数据
   */
  data: {
    user: {},
    listInfo: '',
    page: 2,
    count: 0
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {
    var page = this;

    var times = 0;
    var number = 1;

    page.fromNet = 0;
    getUser(page);

    wx.showNavigationBarLoading();

    var timeOut = setTimeout(function fn() {
      if (page.data.user) {
        console.log('请求历史记录');
        console.log(Date.now());
        wx.request({
          url: URL,
          data: {
            mod: 'api',
            act: 'weapp',
            opt: 'orders',
            session: page.data.user.session,
            page: number
          },
          success: function (res) {
            console.log("历史记录接口:", res);
            if (res.data.code === 0) {
              holdUser(page);

              var listInfo = {};

              var list = res.data.data;
              console.log("历史记录数据:", list);
              var items = [];
              var len = list.length;

              if (len > 0) {
                for (var i = 0; i < len; i++) {
                  if (list[i].status == 1) {
                    list[i].tip = '使用中';
                  } else if (list[i].status == 2) {
                    list[i].tip = '已完成';
                  } else if (list[i].status == 3) {
                    list[i].tip = '已关闭';
                  }

                  list[i].show = false;
                  items.push(list[i]);
                  if (!list[i].return_time) {
                    list[i].return_time = list[i].borrow_time;
                  }
                  if (!list[i].return_name) {
                    list[i].return_name = "登记遗失处理";
                  }
                }

                listInfo.items = items;

                number++;
                console.log('整理后的数据', listInfo);
                var user = page.data.user;
                user.time = user.time + 1500000;

                page.setData({
                  page: number,
                  listInfo: listInfo,
                  user: user
                });

                wx.setStorage({
                  key: 'user',
                  data: user,
                });

              }
              wx.hideNavigationBarLoading();
            } else if (res.data.code == 5) {
              if (!page.working) {
                page.fromNet = 1;
                getUser(page);
              }
              timeOut = setTimeout(fn, 50);
            } else {
              wx.hideNavigationBarLoading();
            }
          },
          fail: function () {
            if (times < 100) {
              timeOut = setTimeout(fn, 50);
            }
          },
          complete: function () {
            times++;
          }
        });
      } else {
        if (times < 100) {
          timeOut = setTimeout(fn, 50);
        }
      }
    }, 50);
  },

  onShow: function () {
    this.setData({
      count: 0
    });
  },

  loadMore: function () {
    wx.showNavigationBarLoading();

    var page = this;
    var number = page.data.page;

    wx.request({
      url: URL,
      data: {
        mod: 'api',
        act: 'weapp',
        opt: 'orders',
        session: page.data.user.session,
        page: number
      },
      success: function (res) {
        console.log("历史记录数据:", res);
        if (res.data.code === 0) {
          holdUser(page);

          var listInfo = {};

          var list = res.data.data;

          var items = page.data.listInfo.items;
          var len = list.length;

          if (len > 0) {
            for (var i = 0; i < len; i++) {
              console.log('list[i]', list[i]);
              if (list[i].status == 1) {
                list[i].tip = '使用中';
              } else if (list[i].status == 2) {
                list[i].tip = '已完成';
              } else if (list[i].status == 3) {
                list[i].tip = '已关闭';
              }

              if (!list[i].return_time) {
                list[i].return_time = list[i].borrow_time;
              }
              if (!list[i].return_name) {
                list[i].return_name = "登记遗失处理";
              }

              list[i].show = false;
              items.push(list[i]);
            }


            listInfo.items = items;

            number++;
            console.log('整理后的数据', listInfo);

            page.setData({
              page: number,
              listInfo: listInfo
            });

            wx.hideNavigationBarLoading();
          } else {
            wx.showToast({
              title: '暂无更多数据'
            });
          }

        } else if (res.data.code == 5) {
          if (page.working) {
            page.fromNet = 1;
            getUser(page);
          }
        } else if (res.data.code == 2) {
          if (page.data.count === 0) {
            wx.showToast({
              title: '暂无更多数据'
            })
          }
          page.setData({
            count: 1
          });
          wx.hideNavigationBarLoading();
        }
      },
      complete: function () {
        wx.hideNavigationBarLoading();
      }
    });
  },

  registerLost: function (e) {
    var page = this;
    var id = e.currentTarget.id;
    console.log('id', id);
    console.log('currentTarget', e.currentTarget);

    wx.showModal({
      title: '',
      content: '如果伞已遗失，将扣减' + page.data.listInfo.items[0].price + '元作为赔付',
      cancelColor: '#040404',
      confirmColor: '#040404',
      confirmText: '登记遗失',
      success: function (res) {
        if (res.confirm) {
          wx.request({
            url: URL,
            data: {
              mod: 'api',
              act: 'weapp',
              opt: 'loss_handle',
              session: page.data.user.session,
              order_id: id
            },
            success: function (res) {
              console.log('遗失登记的内容', res);
              if (res.data.code == 0) {
                holdUser(page);
                var times = 0;
                wx.showModal({
                  title: '',
                  content: '登记遗失已完成，已扣减' + page.data.listInfo.items[0].price + '元',
                  showCancel: false,
                  confirmText: 'OK',
                  confirmColor: '#040404'
                });
                var number = 1;
                var timeOut = setTimeout(function fn() {
                  if (page.data.user) {
                    wx.request({
                      url: URL,
                      data: {
                        mod: 'api',
                        act: 'weapp',
                        opt: 'orders',
                        session: page.data.user.session,
                        page: 1
                      },
                      success: function (res) {
                        console.log("历史记录接口:", res);
                        if (res.data.code === 0) {
                          holdUser(page);

                          var listInfo = {};

                          var list = res.data.data;
                          console.log("历史记录数据:", list);
                          var items = [];
                          var len = list.length;

                          if (len > 0) {
                            for (var i = 0; i < len; i++) {
                              if (list[i].status == 1) {
                                list[i].tip = '使用中';
                              } else if (list[i].status == 2) {
                                list[i].tip = '已完成';
                              } else if (list[i].status == 3) {
                                list[i].tip = '已关闭';
                              }

                              list[i].show = false;
                              items.push(list[i]);
                              if (!list[i].return_time) {
                                list[i].return_time = list[i].borrow_time;
                              }
                              if (!list[i].return_name) {
                                list[i].return_name = "登记遗失处理";
                              }
                            }

                            listInfo.items = items;

                            number++;
                            console.log('整理后的数据', listInfo);

                            page.setData({
                              page: number,
                              listInfo: listInfo
                            })
                          }
                          wx.hideNavigationBarLoading();
                        } else if (res.data.code == 5) {
                          if (!page.working) {
                            page.fromNet = 1;
                            getUser(page);
                          }
                          if (times < 100) {
                            timeOut = setTimeout(fn, 50);
                          }
                        } else {
                          wx.hideNavigationBarLoading();
                        }
                      },
                      fail: function () {
                        if (times < 100) {
                          timeOut = setTimeout(fn, 50);
                        }
                      },
                      complete: function () {
                        times++;
                      }
                    });
                  } else {
                    if (times < 100) {
                      timeOut = setTimeout(fn, 50);
                    }
                  }
                }, 50);
              } else if (res.data.code == 5) {
                if (!page.working) {
                  page.fromNet = 1;
                  getUser(page);
                }
              } else {
                if (res.data.code == 2) {
                  wx.showModal({
                    title: '登记遗失未成功',
                    content: '借伞后两分钟内登记遗失也会失败',
                    showCancel: 'false',
                    confirmText: 'OK',
                    confirmColor: '#040404'
                  })
                }
              }
            },
            fail: function () {
              wx.showToast({
                title: '网络忙，请重试'
              });
            }
          })
        } else {
          wx.showToast({
            title: '登记遗失取消'
          });
        }
      }
    })
  },

  choose: function (e) {
    var page = this;

    var id = e.currentTarget.id;

    var list = page.data.listInfo;

    var items = list.items;

    for (var i = 0; i < items.length; i++) {
      if (i == id) {
        items[i].show = !items[i].show;
        continue;
      }
      items[i].show = false;
    }

    console.log(list);

    page.setData({
      listInfo: list
    })
  },

  goToGuide: function () {
    wx.navigateTo({
      url: '../guide/guide',
    })
  }
})