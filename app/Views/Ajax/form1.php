
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

    <title>Hello, world!</title>
  </head>
  <body>
  <h2 class="text-center">Simple iquery Validation Form 1</h2>
   <div class="container">
     
     <form id="user_form" enctype="multipart/form-data"  class="row g-3">

  <div class="col-md-6">
    <label for="" class="form-label">Full Name</label>
    <input type="text" name="full_name"  id="full_name" class="form-control" id="" aria-describedby="">
    <div id="full_name_val" class=""></div>   
  </div>

  <div class="col-md-6">
    <label for="" class="form-label">Mobile No</label>
    <input type="text" name="mobile_no" id="mobile_no" class="form-control" id="" aria-describedby="">
    <div id="mobile_no_val" class=""></div>   
  </div>

  <div class="col-md-6">
    <label for="" class="form-label">Email address</label>
    <input type="text" name="email" id="email" class="form-control" id="" aria-describedby=""> 
    <div id="email_val" class=""></div>   
  </div>

  <div class="col-md-6">
    <label for="" class="form-label">Country</label>
    <input type="text" name="country" id="country" class="form-control" id="" aria-describedby="">
    <div id="country_val" class=""></div>    
  </div>

  <div class="col-md-6">
    <label for="" class="form-label">State</label>
    <input type="text" name="state" id="state" class="form-control" id="" aria-describedby=""> 
    <div id="state_val" class=""></div>   
  </div>

  <div class="col-md-6">
    <label for="exampleInputPassword1" class="form-label">City</label>
    <input type="text" name="city" id="city" class="form-control" id="exampleInputPassword1">
    <div id="city_val" class=""></div>
  </div>

  <div class="col-md-6">
            <label for="file">File:</label>
            <input type="file" class="form-control" id="file" name="file"/>
            <div id="file_val" class=""></div>
  </div>

  <div class="col-md-6">

    <label for="">Select Radio Option</label><div id="radio_val" class="">Test</div>
     
        <div class="form-check">
          <label class="form-check-label">
            <input class="form-check-input" type="radio" name="gridRadios" id="gridRadios1" value="option1">
            Option 1
          </label>
        </div>
        <div class="form-check">
          <label class="form-check-label">
            <input class="form-check-input" type="radio" name="gridRadios" id="gridRadios2" value="option2">
            Option 2
          </label>
        </div>
        <div class="form-check">
          <label class="form-check-label">
            <input class="form-check-input" type="radio" name="gridRadios" id="gridRadios3" value="option3" >
            Option 3
          </label>
        </div>   
  </div>




  <div class="col-md-6">
  <button type="submit" id="submit" class="btn btn-primary">Submit</button>
  <a href="<?php echo base_url()?>User"  class="btn btn-primary">Back</a>
  </div>
  
  
 
</form>

   </div>
  </body>
  <script src="http://code.jquery.com/jquery-1.9.1.js"></script>
  <script>
     $(document).ready(function()
     {

      $('form').on('submit', function (e) {

      e.preventDefault();

      var name  = $('#full_name').val(); 
      var email   = $('#email').val();
      var mobile_no   = $('#mobile_no').val();
      var country   = $('#country').val();
      var state   = $('#state').val();
      var city   = $('#city').val();

      if(name == "" || email == "" || mobile_no =="" || country=="" || state=="" || city=="")
      {
        //set_error2('full_name','Full Name canot be blank.');
        // set_error2('email','Email canot be blank.');
        // set_error2('mobile_no','Mobile No canot be blank.');
        // set_error2('country','Country canot be blank.');
        // set_error2('state','State canot be blank.');
        // set_error2('city','City canot be blank.');

        set_error2('radio','Plz select radio.');

        //alert('Enter Valid data');
        return false;
      }

      $.ajax({        
        url: '<?php echo base_url();?>Ajax/save_form1', 
        type: "POST",
        data:  new FormData(this),
        contentType: false,
        cache: false,
        processData:false,

        success: function (res) {          
          if(res == "ok")
          {
            remove_error2('full_name');
            remove_error2('mobile_no');
            remove_error2('email');
            alert("Record Saved Successfully");

            //window.location.href = "<?php echo base_url()?>/User";
          }
          else
          {
            var data = $.parseJSON(res);
            jQuery.each(data, function(index, item) 
            {
              if(item !="")
              {
                set_error2(index,item); 
              }
              else
              {
                remove_error2(index);
              }              
            });
            //console.log(data);
            //alert(data);
          }
        }
      });
      }); 

      function set_error2(e,msg)
      {
        console.log('hi');
        $('#'+e).addClass('is-invalid');
        $('#'+e+'_val').addClass('invalid-feedback');
        $('#'+e+'_val').html(msg);        
      }   
      function remove_error2(e)
      {
        console.log(e);
        $('#'+e).removeClass('is-invalid');       
        $('#'+e+'_val').html("");        
      }   
});
    </script>
</html>