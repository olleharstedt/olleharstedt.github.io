type point = {
  x : int;
  y : int;
}

type rectangle = {
  bottom_left : point;
  top_right : point;
}

type circle = {
  center: point;
  radius : int;
}

type shape =
    | Point of point
    | Rectangle of rectangle
    | Circle of circle

let area_of_shape (s : shape) : int =
  match s with
    | Point p -> 0
    | Rectangle {bottom_left; top_right} -> 1
    | Circle {center; radius} -> 2

module Surface = struct
    type t
end

let draw_shape (s : shape) (surface : Surface.t) : unit =
    ()

let l = [
    Point {x = 1; y = 2};
    Rectangle {bottom_left = {x = 2; y = 3}; top_right = {x = 3; y = 4}};
    Circle {center = {x = 5; y = 5}; radius = 2}
]

let _ =
    let areas = List.map (fun s -> area_of_shape s) l in
    ()
