<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    http_response_code(403);
    exit('Unauthorized');
}
if(!empty($_POST["category"])) 
{
$category=$_POST["category"];
$sql=$dbh->prepare("SELECT * FROM tblpackage WHERE cate_id=:category");
$sql->execute(array(':category' => $category));   
?>
<option value="">Select Package</option>
<?php
while($row =$sql->fetch())
{
?>
<option value="<?php echo $row["id"]; ?>"><?php echo $row["PackageName"]; ?></option>
<?php
}
}
?>