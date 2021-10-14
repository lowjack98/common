// vim600: fdm=marker
///////////////////////////////////////////
// author     : Ben Kietzman
// begin      : 2021-10-12
// copyright  : kietzman.org
// email      : ben@kietzman.org
///////////////////////////////////////////
export default
{
  // {{{ data
  data()
  {
    let data =
    {
    };
    common.menuSet('Login', null);
    return data;
  },
  // }}}
  // {{{ template
  template: `
    <div>
      <br><br><br>
      <div v-show="common.login.message" style="color:red;font-weight:bold;"><br><br>{{common.login.message}}<br><br></div>
      <div v-show="common.login.info" style="color:orange;"><br><br>{{common.login.info}}<br><br></div>
      <div v-show="common.login.showForm" class="row" style="width:50%;">
        <h3 class="page-header">{{common.login.login.title}}</h3>
        <div class="col-md-5" style="padding:10px;"><input class="form-control" type="text" id="login_userid" v-model="common.login.login.userid" maxlength="20" v-on:keyup="$event.keyCode == 13 && submit()" placeholder="User"></div>
        <div class="col-md-5" style="padding:10px;"><input class="form-control" type="password" v-model="common.login.login.password" maxlength="64" v-on:keyup="$event.keyCode == 13 && common.loginProcess()" placeholder="Password"></div>
        <div class="col-md-2" style="padding:10px;"><button class="btn btn-primary" v-on:click="common.loginProcess()" style="float:right;">Login</button></div>
      </div>
    </div>
  `
  // }}}
}
