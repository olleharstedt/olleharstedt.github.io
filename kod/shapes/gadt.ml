type point
type rectangle
type circle

type _ shape = 
  | Point : int * int -> point shape
  | Rec : point shape * point shape -> rectangle shape

let new_point () = Point (1, 2)

let new_rec () = Rec (Point (1, 2), Point (2, 3))

(** Existential wrapper *)
type any_shape = Any : 'a shape -> any_shape

let area_of_shape : type a. a shape -> int = fun s -> match s with
  | Point (x, y) -> 10
  | Rec (Point (bx, by), Point (tx, ty))-> 20

let l = [Any (new_point ()); Any (new_rec ());]

let () =
  let areas = List.map (fun (Any s) -> area_of_shape s) l in
  ()
